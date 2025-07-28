<?php

namespace Winter\Search\Console;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Scout\Events\ModelsImported;
use Symfony\Component\Console\Attribute\AsCommand;
use Winter\Search\Behaviors\Halcyon\Searchable as HalcyonSearchable;
use Winter\Search\Behaviors\Searchable;
use Winter\Storm\Console\Command;

#[AsCommand(name: 'search:import')]
class ImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:import
            {model : Class name of model to bulk import}
            {--c|chunk= : The number of records to import at a time (Defaults to configuration value: `search.chunk.searchable`)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the given model into the search index';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function handle(Dispatcher $events)
    {
        $class = $this->argument('model');

        $model = new $class;

        if (
            !$model->isClassExtendedWith(Searchable::class)
            && !$model->isClassExtendedWith(HalcyonSearchable::class)
        ) {
            $this->error(sprintf(
                'Class %s does not implement the %s or the %s behavior',
                $class,
                Searchable::class,
                HalcyonSearchable::class,
            ));
            return 1;
        }

        $events->listen(ModelsImported::class, function ($event) use ($class) {
            $key = $event->models->last()->getScoutKey();

            $this->line('<comment>Imported ['.$class.'] models up to ID:</comment> '.$key);
        });

        $model::makeAllSearchable($this->option('chunk'));

        $events->forget(ModelsImported::class);

        $this->info('All ['.$class.'] records have been imported.');
    }
}

<?php

namespace Winter\Search\Console;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Scout\Console\ImportCommand as BaseImportCommand;
use Winter\Search\Behaviors\Halcyon\Searchable as HalcyonSearchable;
use Winter\Search\Behaviors\Searchable;

class ImportCommand extends BaseImportCommand
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
                HalyconSearchable::class,
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

<?php

namespace Winter\Search\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Winter\Search\Behaviors\Halcyon\Searchable as HalcyonSearchable;
use Winter\Search\Behaviors\Searchable;
use Winter\Storm\Console\Command;

#[AsCommand(name: 'search:flush')]
class FlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:flush {model : Class name of the model to flush}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush all of the model's records from the index";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
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

        $model::removeAllFromSearch();

        $this->info('All ['.$class.'] records have been flushed.');
    }
}

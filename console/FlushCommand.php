<?php

namespace Winter\Search\Console;

use Laravel\Scout\Console\FlushCommand as BaseFlushCommand;
use Winter\Search\Behaviors\Halcyon\Searchable as HalcyonSearchable;
use Winter\Search\Behaviors\Searchable;

class FlushCommand extends BaseFlushCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:flush {model}';

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
                HalyconSearchable::class,
            ));
            return 1;
        }

        $model::removeAllFromSearch();

        $this->info('All ['.$class.'] records have been flushed.');
    }
}

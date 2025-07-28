<?php

namespace Winter\Search\Console;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Winter\Search\Classes\EngineManager;
use Winter\Storm\Console\Command;

#[AsCommand(name: 'search:delete-all-indexes')]
class DeleteAllIndexesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:delete-all-indexes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all indexes';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(EngineManager $manager)
    {
        $engine = $manager->engine();

        $driver = config('search.driver');

        if (! method_exists($engine, 'deleteAllIndexes')) {
            return $this->error('The ['.$driver.'] engine does not support deleting all indexes.');
        }

        try {
            $manager->engine()->deleteAllIndexes();

            $this->info('All indexes deleted successfully.');
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }
}

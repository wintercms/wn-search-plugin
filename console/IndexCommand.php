<?php

namespace Winter\Search\Console;

use Exception;
use Laravel\Scout\Contracts\UpdatesIndexSettings;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Exceptions\NotSupportedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Winter\Search\Classes\EngineManager;
use Winter\Storm\Console\Command;
use Winter\Storm\Database\Traits\SoftDelete;
use Winter\Storm\Support\Str;

#[AsCommand(name: 'search:index')]
class IndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index
            {name : The name of the index}
            {--k|key= : The name of the primary key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an index';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(EngineManager $manager)
    {
        $engine = $manager->engine();

        try {
            $options = [];

            if ($this->option('key')) {
                $options = ['primaryKey' => $this->option('key')];
            }

            if (class_exists($modelName = $this->argument('name'))) {
                $model = new $modelName;
            }

            $name = $this->indexName($this->argument('name'));

            $this->createIndex($engine, $name, $options);

            if ($engine instanceof UpdatesIndexSettings) {
                $driver = config('search.driver');

                $class = isset($model) ? get_class($model) : null;

                $settings = config('search.'.$driver.'.index-settings.'.$name)
                                ?? config('search.'.$driver.'.index-settings.'.$class)
                                ?? [];

                if (isset($model) &&
                    config('search.soft_delete', false) &&
                    in_array(SoftDelete::class, class_uses_recursive($model))) {
                    $settings = $engine->configureSoftDeleteFilter($settings);
                }

                if ($settings) {
                    $engine->updateIndexSettings($name, $settings);
                }
            }

            $this->info('Synchronised index ["'.$name.'"] successfully.');
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Create a search index.
     *
     * @param  \Laravel\Scout\Engines\Engine  $engine
     * @param  string  $name
     * @param  array  $options
     * @return void
     */
    protected function createIndex(Engine $engine, $name, $options): void
    {
        try {
            $engine->createIndex($name, $options);
        } catch (NotSupportedException) {
            return;
        }
    }

    /**
     * Get the fully-qualified index name for the given index.
     *
     * @param  string  $name
     * @return string
     */
    protected function indexName($name)
    {
        if (class_exists($name)) {
            return (new $name)->indexableAs();
        }

        $prefix = config('search.prefix');

        return !Str::startsWith($name, $prefix) ? $prefix.$name : $name;
    }
}

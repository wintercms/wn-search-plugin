<?php

namespace Winter\Search\Console;

use Exception;
use Laravel\Scout\Contracts\UpdatesIndexSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Winter\Search\Classes\EngineManager;
use Winter\Storm\Console\Command;
use Winter\Storm\Database\Traits\SoftDelete;

#[AsCommand(name: 'search:sync-index-settings')]
class SyncIndexSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:sync-index-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync your configured index settings with your search engine (Meilisearch)';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(EngineManager $manager)
    {
        $engine = $manager->engine();

        $driver = config('search.driver');

        if (!$engine instanceof UpdatesIndexSettings) {
            return $this->error('The "'.$driver.'" engine does not support updating index settings.');
        }

        try {
            $indexes = (array) config('search.'.$driver.'.index-settings', []);

            if (count($indexes)) {
                foreach ($indexes as $name => $settings) {
                    if (!is_array($settings)) {
                        $name = $settings;

                        $settings = [];
                    }

                    if (class_exists($name)) {
                        $model = new $name;
                    }

                    if (isset($model) &&
                        config('search.soft_delete', false) &&
                        in_array(SoftDelete::class, class_uses_recursive($model))) {
                        $settings = $engine->configureSoftDeleteFilter($settings);
                    }

                    $engine->updateIndexSettings($indexName = $this->indexName($name), $settings);

                    $this->info('Settings for the ['.$indexName.'] index synced successfully.');
                }
            } else {
                $this->info('No index settings found for the "'.$driver.'" engine.');
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
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
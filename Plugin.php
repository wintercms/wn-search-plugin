<?php

namespace Winter\Search;

use Config;
use Laravel\Scout\EngineManager as ScoutEngineManager;
use System\Classes\PluginBase;
use MeiliSearch\Client as MeiliSearch;
use Winter\Search\Classes\CorePluginManager;
use Winter\Search\Classes\EngineManager;

/**
 * Search plugin.
 *
 * Adds full-text search capabilities to Winter.
 *
 * @author Ben Thomson <git@alfreido.com>
 */
class Plugin extends PluginBase
{
    /**
     * Language string prefix
     */
    const LANG = 'winter.search::lang.';

    /**
     * @inheritDoc
     */
    public function pluginDetails()
    {
        return [
            'name'        => self::LANG . 'plugin.name',
            'description' => self::LANG . 'plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-search',
            'homepage'    => 'https://github.com/wintercms/wn-search-plugin',
        ];
    }

    /**
     * @inheritDoc
     */
    public function register()
    {
        if (class_exists(MeiliSearch::class)) {
            $this->app->singleton(MeiliSearch::class, function ($app) {
                $config = $app['config']->get('search.meilisearch');

                return new MeiliSearch($config['host'], $config['key']);
            });
        }

        // Use our own Engine Manager and alias the Laravel Scout manager to our own.
        $this->app->singleton(EngineManager::class, function ($app) {
            return new EngineManager($app);
        });
        $this->app->alias(EngineManager::class, ScoutEngineManager::class);
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        // Load configuration
        Config::set('search', Config::get('winter.search::search'));

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishedConfig();
        }

        $corePlugins = CorePluginManager::instance();
        $corePlugins->attachCorePlugins();
    }

    /**
     * Register commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            \Winter\Search\Console\DeleteIndexCommand::class,
            \Winter\Search\Console\FlushCommand::class,
            \Winter\Search\Console\ImportCommand::class,
            \Winter\Search\Console\IndexCommand::class,
        ]);
    }

    /**
     * Register published configurations.
     *
     * @return void
     */
    protected function registerPublishedConfig()
    {
        $this->publishes([
            __DIR__ . '/config/search.php' => implode(DIRECTORY_SEPARATOR, [
                $this->app->configPath(),
                'winter',
                'search',
                'search.php'
            ])
        ]);
    }

    /**
     * @inheritDoc
     */
    public function registerComponents()
    {
        return [
            'Winter\Search\Components\Search' => 'search',
        ];
    }

    /**
     * Register search handlers.
     *
     * @return array
     */
    public function registerSearchHandlers()
    {
        return CorePluginManager::instance()->getHandlers();
    }
}

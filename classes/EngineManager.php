<?php

namespace Winter\Search\Classes;

use Config;
use Algolia\AlgoliaSearch\Config\SearchConfig;
use Algolia\AlgoliaSearch\SearchClient as Algolia;
use Algolia\AlgoliaSearch\Support\UserAgent;
use Laravel\Scout\EngineManager as BaseEngineManager;
use MeiliSearch\Client as MeiliSearch;
use Winter\Search\Engines\AlgoliaEngine;
use Winter\Search\Engines\CollectionEngine;
use Winter\Search\Engines\DatabaseEngine;
use Winter\Search\Engines\MeiliSearchEngine;
use Winter\Search\Engines\NullEngine;

/**
 * Engine Manager wrapper.
 *
 * This provides compatibility with our configuration, and uses our own Engine classes.
 */
class EngineManager extends BaseEngineManager
{
    /**
     * Create an Algolia engine instance.
     *
     * @return \Winter\Search\Engines\AlgoliaEngine
     */
    public function createAlgoliaDriver()
    {
        $this->ensureAlgoliaClientIsInstalled();

        UserAgent::addCustomUserAgent('Winter Search', '1.0.0');

        $config = SearchConfig::create(
            Config::get('search.algolia.id'),
            Config::get('search.algolia.secret')
        )->setDefaultHeaders(
            $this->defaultAlgoliaHeaders()
        );

        if (is_int($connectTimeout = Config::get('search.algolia.connect_timeout'))) {
            $config->setConnectTimeout($connectTimeout);
        }

        if (is_int($readTimeout = Config::get('search.algolia.read_timeout'))) {
            $config->setReadTimeout($readTimeout);
        }

        if (is_int($writeTimeout = Config::get('search.algolia.write_timeout'))) {
            $config->setWriteTimeout($writeTimeout);
        }

        return new AlgoliaEngine(Algolia::createWithConfig($config), Config::get('search.soft_delete'));
    }

    /**
     * Set the default Algolia configuration headers.
     *
     * @return array
     */
    protected function defaultAlgoliaHeaders()
    {
        if (!Config::get('search.identify')) {
            return [];
        }

        $headers = [];

        if (
            !Config::get('app.debug') &&
            filter_var($ip = request()->ip(), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        ) {
            $headers['X-Forwarded-For'] = $ip;
        }

        if (($user = request()->user()) && method_exists($user, 'getKey')) {
            $headers['X-Algolia-UserToken'] = $user->getKey();
        }

        return $headers;
    }

    /**
     * Create an MeiliSearch engine instance.
     *
     * @return \Winter\Search\Engines\MeiliSearchEngine
     */
    public function createMeilisearchDriver()
    {
        $this->ensureMeiliSearchClientIsInstalled();

        return new MeiliSearchEngine(
            $this->container->make(MeiliSearch::class),
            Config::get('search.soft_delete', false)
        );
    }

    /**
     * Create a database engine instance.
     *
     * @return \Winter\Search\Engines\DatabaseEngine
     */
    public function createDatabaseDriver()
    {
        return new DatabaseEngine;
    }

    /**
     * Create a collection engine instance.
     *
     * @return \Winter\Search\Engines\CollectionEngine
     */
    public function createCollectionDriver()
    {
        return new CollectionEngine;
    }

    /**
     * Create a null engine instance.
     *
     * @return \Winter\Search\Engines\NullEngine
     */
    public function createNullDriver()
    {
        return new NullEngine;
    }

    /**
     * Get the default Winter Search driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        if (is_null($driver = Config::get('search.driver'))) {
            return 'null';
        }

        return $driver;
    }
}

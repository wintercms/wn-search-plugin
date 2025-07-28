<?php

namespace Winter\Search\Classes;

use Winter\Storm\Support\Facades\Config;
use Algolia\AlgoliaSearch\Support\AlgoliaAgent as Algolia4UserAgent;
use Algolia\AlgoliaSearch\Support\UserAgent as Algolia3UserAgent;
use Laravel\Scout\EngineManager as BaseEngineManager;
use MeiliSearch\Client as MeiliSearch;
use Typesense\Client as Typesense;
use Winter\Search\Engines\Algolia3Engine;
use Winter\Search\Engines\Algolia4Engine;
use Winter\Search\Engines\CollectionEngine;
use Winter\Search\Engines\DatabaseEngine;
use Winter\Search\Engines\MeiliSearchEngine;
use Winter\Search\Engines\NullEngine;
use Winter\Search\Engines\TypesenseEngine;

/**
 * Engine Manager wrapper.
 *
 * This provides compatibility with our configuration, and uses our own Engine classes.
 */
class EngineManager extends BaseEngineManager
{
    /**
     * Create an Algolia v3 engine instance.
     *
     * @return \Winter\Search\Engines\Algolia3Engine
     */
    protected function configureAlgolia3Driver()
    {
        Algolia3UserAgent::addCustomUserAgent('Winter Search', '1.0.0');

        return Algolia3Engine::make(
            config: Config::get('search.algolia'),
            headers: $this->defaultAlgoliaHeaders(),
            softDelete: Config::get('search.soft_delete')
        );
    }

    /**
     * Create an Algolia v4 engine instance.
     *
     * @return \Winter\Search\Engines\Algolia4Engine
     */
    protected function configureAlgolia4Driver()
    {
        Algolia4UserAgent::addCustomUserAgent('Winter Search', '1.0.0');

        return Algolia4Engine::make(
            config: Config::get('search.algolia'),
            headers: $this->defaultAlgoliaHeaders(),
            softDelete: Config::get('search.soft_delete')
        );
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
     * Create a Typesense engine instance.
     *
     * @return \Laravel\Scout\Engines\TypesenseEngine
     *
     * @throws \Typesense\Exceptions\ConfigError
     */
    public function createTypesenseDriver()
    {
        $config = config('search.typesense');
        $this->ensureTypesenseClientIsInstalled();

        return new TypesenseEngine(new Typesense($config['client-settings']), $config['max_total_results'] ?? 1000);
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

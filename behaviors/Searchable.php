<?php

namespace Winter\Search\Behaviors;

use Winter\Search\Classes\ModelObserver;
use Winter\Search\Classes\SearchableScope;
use Winter\Search\Classes\EngineManager;
use Winter\Storm\Extension\ExtensionBase;
use Winter\Storm\Support\Arr;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Database\Traits\SoftDelete;
use Illuminate\Support\Collection as BaseCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Scout;
use Laravel\Scout\Searchable as BaseSearchable;

class Searchable extends ExtensionBase
{
    use BaseSearchable;

    /**
     * @var \Winter\Storm\Database\Model $model The model instance being extended
     */
    protected $model;

    /**
     * @var boolean Whether the global search functionality has been booted.
     */
    public static $booted = false;

    /**
     * @var string[] Classes that have been booted with this behaviour.
     */
    public static $bootedClasses = [];

    /**
     * Constructor for the behaviour.
     *
     * Attaches listeners to the model.
     *
     * @param \Winter\Storm\Database\Model $model
     */
    public function __construct($model)
    {
        $this->model = $model;
        static::$extendableStaticCalledClass = get_class($this->model);

        if (!in_array(static::getCalledExtensionClass(), static::$bootedClasses)) {
            $this->bootSearchable();
        }
        if (!static::$booted) {
            $this->registerSearchableMacros();
            static::$booted = true;
        }
    }

    /**
     * Boots the searchable functionality.
     *
     * @return void
     */
    protected function bootSearchable()
    {
        $class = static::getCalledExtensionClass();
        static::$bootedClasses[] = $class;

        static::getCalledExtensionClass()::addGlobalScope(new SearchableScope);
        static::getCalledExtensionClass()::observe(new ModelObserver);
    }

    /**
     * Register the searchable macros.
     *
     * @return void
     */
    protected function registerSearchableMacros()
    {
        $self = $this;

        BaseCollection::macro('searchable', function () use ($self) {
            $self->queueMakeSearchable($this);
        });

        BaseCollection::macro('unsearchable', function () use ($self) {
            $self->queueRemoveFromSearch($this);
        });
    }

    /**
     * Dispatch the job to make the given models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function queueMakeSearchable($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        if (!Config::get('search.queue')) {
            return $models->first()->searchableUsing()->update($models);
        }

        dispatch((new Scout::$makeSearchableJob($models))
                ->onQueue($models->first()->syncWithSearchUsingQueue())
                ->onConnection($models->first()->syncWithSearchUsing()));
    }

    /**
     * Dispatch the job to make the given models unsearchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function queueRemoveFromSearch($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        if (!Config::get('search.queue')) {
            return $models->first()->searchableUsing()->delete($models);
        }

        dispatch(new Scout::$removeFromSearchJob($models))
            ->onQueue($models->first()->syncWithSearchUsingQueue())
            ->onConnection($models->first()->syncWithSearchUsing());
    }

    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search($query = '', $callback = null)
    {
        $model = static::getCalledExtensionClass();

        return app(Builder::class, [
            'model' => new $model,
            'query' => $query,
            'callback' => $callback,
            'softDelete'=> static::usesSoftDelete() && Config::get('search.soft_delete', false),
        ]);
    }

    /**
     * Perform a search against the model's indexed data.
     *
     * This is the same as the static::search() method, except that it can run on an instance of the model.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public function doSearch($query = '', $callback = null)
    {
        return app(Builder::class, [
            'model' => $this->model,
            'query' => $query,
            'callback' => $callback,
            'softDelete'=> static::usesSoftDelete() && Config::get('search.soft_delete', false),
        ]);
    }

    /**
     * Make all instances of the model searchable.
     *
     * @param  int  $chunk
     * @return void
     */
    public static function makeAllSearchable($chunk = null)
    {
        $model = static::getCalledExtensionClass();
        $self = new $model;

        $softDelete = static::usesSoftDelete() && config('scout.soft_delete', false);

        $self->newQuery()
            ->when(true, function ($query) use ($self) {
                static::makeAllSearchableUsing($query);
            })
            ->when($softDelete, function ($query) {
                $query->withTrashed();
            })
            ->orderBy($self->getKeyName())
            ->searchable($chunk);
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function makeAllSearchableUsing($query)
    {
        return $query;
    }

    /**
     * Make the given model instance searchable.
     *
     * @return void
     */
    public function searchable()
    {
        $this->model->newCollection([$this->model])->searchable();
    }

    /**
     * Remove all instances of the model from the search index.
     *
     * @return void
     */
    public static function removeAllFromSearch()
    {
        $model = static::getCalledExtensionClass();
        $self = new $model;

        $self->searchableUsing()->flush($self);
    }

    /**
     * Get the requested models from an array of object IDs.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $ids
     * @return mixed
     */
    public function getScoutModelsByIds(Builder $builder, array $ids)
    {
        return $this->model->queryScoutModelsByIds($builder, $ids)->get();
    }

    /**
     * Get a query builder for retrieving the requested models from an array of object IDs.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $ids
     * @return mixed
     */
    public function queryScoutModelsByIds(Builder $builder, array $ids)
    {
        $query = static::usesSoftDelete()
            ? $this->model->withTrashed() : $this->model->newQuery();

        if ($builder->queryCallback) {
            call_user_func($builder->queryCallback, $query);
        }

        $whereIn = in_array($this->model->getKeyType(), ['int', 'integer']) ?
            'whereIntegerInRaw' :
            'whereIn';

        return $query->{$whereIn}(
            $this->getScoutKeyName(),
            $ids
        );
    }

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return Config::get('search.prefix') . $this->model->getTable();
    }

    /**
     * Get the queue connection that should be used when syncing.
     *
     * @return string
     */
    public function syncWithSearchUsing()
    {
        return Config::get('search.queue.connection') ?: Config::get('queue.default');
    }

    /**
     * Get the queue that should be used with syncing.
     *
     * @return string
     */
    public function syncWithSearchUsingQueue()
    {
        return Config::get('search.queue.queue');
    }

    /**
     * Enable search syncing for this model.
     *
     * @return void
     */
    public static function enableSearchSyncing()
    {
        ModelObserver::enableSyncingFor(static::getCalledExtensionClass());
    }

    /**
     * Disable search syncing for this model.
     *
     * @return void
     */
    public static function disableSearchSyncing()
    {
        ModelObserver::disableSyncingFor(static::getCalledExtensionClass());
    }

    /**
     * Get the Scout engine for the model.
     *
     * @return mixed
     */
    public function searchableUsing()
    {
        return app(EngineManager::class)->engine();
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        if ($this->model->methodExists('getSearchableArray')) {
            return $this->model->getSearchableArray();
        } elseif ($this->model->propertyExists('searchable')) {
            $searchableData = [];
            $modelAttributes = Arr::dot($this->model->getAttributes());

            foreach ($this->model->searchable as $attribute) {
                // Convert to dot notation
                $attribute = str_replace(['[', ']'], ['.', ''], $attribute);
                Arr::set($searchableData, $attribute, $modelAttributes[$attribute] ?? null);
            }

            return $searchableData;
        }

        return $this->model->toArray();
    }

    /**
     * Determine if the current class should use soft deletes with searching.
     *
     * @return bool
     */
    protected static function usesSoftDelete()
    {
        return in_array(SoftDelete::class, class_uses_recursive(static::getCalledExtensionClass()));
    }

    /**
     * Get the value used to index the model.
     *
     * @return mixed
     */
    public function getSearchKey()
    {
        return $this->model->getKey();
    }

    /**
     * Get the key name used to index the model.
     *
     * @return mixed
     */
    public function getSearchKeyName()
    {
        return $this->model->getQualifiedKeyName();
    }

    /**
     * Get the value used to index the model.
     *
     * This is overridden by the "getSearchKey" method that normalises the method name for this plugin.
     *
     * @return mixed
     */
    final public function getScoutKey()
    {
        return $this->getSearchKey();
    }

    /**
     * Get the key name used to index the model.
     *
     * This is overridden by the "getSearchKeyName" method that normalises the method name for this plugin.
     *
     * @return mixed
     */
    final public function getScoutKeyName()
    {
        return $this->getSearchKeyName();
    }
}

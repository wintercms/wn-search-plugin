<?php

namespace Winter\Search\Traits;

use Config;
use Laravel\Scout\Builder;
use Laravel\Scout\Scout;
use Laravel\Scout\Searchable as BaseSearchable;
use Winter\Search\Classes\EngineManager;
use Winter\Search\Classes\ModelObserver;
use Winter\Search\Classes\SearchableScope;
use Winter\Storm\Database\Traits\SoftDelete;

/**
 * Searchable trait wrapper.
 *
 * Includes the base Searchable trait but uses our own classes where necessary.
 */
trait Searchable
{
    use BaseSearchable;

    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootSearchable()
    {
        static::addGlobalScope(new SearchableScope);

        static::observe(new ModelObserver);

        (new static)->registerSearchableMacros();
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
        return app(Builder::class, [
            'model' => new static,
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
        $self = new static;

        $softDelete = static::usesSoftDelete() && Config::get('search.soft_delete', false);

        $self->newQuery()
            ->when(true, function ($query) use ($self) {
                $self->makeAllSearchableUsing($query);
            })
            ->when($softDelete, function ($query) {
                $query->withTrashed();
            })
            ->orderBy($self->getKeyName())
            ->searchable($chunk);
    }

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return Config::get('search.prefix') . $this->getTable();
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        if (property_exists($this, 'searchable')) {
            $searchableData = [];

            foreach ($this->searchable as $attribute) {
                $searchableData[$attribute] = $this->{$attribute};
            }

            return $searchableData;
        }

        return $this->toArray();
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
        ModelObserver::enableSyncingFor(get_called_class());
    }

    /**
     * Disable search syncing for this model.
     *
     * @return void
     */
    public static function disableSearchSyncing()
    {
        ModelObserver::disableSyncingFor(get_called_class());
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
     * Determine if the current class should use soft deletes with searching.
     *
     * @return bool
     */
    protected static function usesSoftDelete()
    {
        return in_array(SoftDelete::class, class_uses_recursive(get_called_class()));
    }
}

<?php

namespace Winter\Search\Classes;

use Config;
use Laravel\Scout\ModelObserver as BaseModelObserver;
use Winter\Storm\Halcyon\Model;

/**
 * Halcyon Model Observer.
 *
 * Provides compatibility with our Halcyon models.
 */
class HalcyonModelObserver extends BaseModelObserver
{
    /**
     * Create a new observer instance.
     *
     * @return void
     */
    public function __construct(Model $model)
    {
        $this->afterCommit = Config::get('search.after_commit', false);
        $this->usingSoftDeletes = Config::get('search.soft_delete', false);

        $model::extend(function ($model) {
            $model->bindEvent('model.afterSave', function () use ($model) {
                if (static::syncingDisabledFor($model)) {
                    return;
                }

                if (! $this->forceSaving && ! $model->searchIndexShouldBeUpdated()) {
                    return;
                }

                if (! $model->shouldBeSearchable()) {
                    if ($model->wasSearchableBeforeUpdate()) {
                        $model->unsearchable();
                    }

                    return;
                }

                $model->searchable();
            });

            $model->bindEvent('model.afterDelete', function () use ($model) {
                if (static::syncingDisabledFor($model)) {
                    return;
                }

                if (! $model->wasSearchableBeforeDelete()) {
                    return;
                }

                if ($this->usingSoftDeletes && $this->usesSoftDelete($model)) {
                    $this->whileForcingUpdate(function () use ($model) {
                        $this->saved($model);
                    });
                } else {
                    $model->unsearchable();
                }
            });
        });
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * Halcyon Models do not allow soft-deleting, so this is always false.
     *
     * @param  \Winter\Storm\Database\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return false;
    }
}

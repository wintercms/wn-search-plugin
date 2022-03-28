<?php

namespace Winter\Search\Classes;

use Config;
use Laravel\Scout\ModelObserver as BaseModelObserver;
use Winter\Storm\Database\Traits\SoftDelete;

/**
 * Model Observer wrapper.
 *
 * Provides compatibility with our own configuration and Soft Delete trait.
 */
class ModelObserver extends BaseModelObserver
{
    /**
     * Create a new observer instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->afterCommit = Config::get('search.after_commit', false);
        $this->usingSoftDeletes = Config::get('search.soft_delete', false);
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Winter\Storm\Database\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDelete::class, class_uses_recursive($model));
    }
}

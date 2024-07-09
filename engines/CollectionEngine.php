<?php

namespace Winter\Search\Engines;

use Laravel\Scout\Engines\CollectionEngine as BaseCollectionEngine;
use Winter\Storm\Database\Traits\SoftDelete;
use Winter\Storm\Support\Arr;

class CollectionEngine extends BaseCollectionEngine
{
    /**
     * Ensure that soft delete handling is properly applied to the query.
     *
     * @param  \Winter\Search\Classes\Builder  $builder
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    protected function ensureSoftDeletesAreHandled($builder, $query)
    {
        if (Arr::get($builder->wheres, '__soft_deleted') === 0) {
            return $query->withoutTrashed();
        } elseif (Arr::get($builder->wheres, '__soft_deleted') === 1) {
            return $query->onlyTrashed();
        } elseif (in_array(SoftDelete::class, class_uses_recursive(get_class($builder->model))) &&
                  config('search.soft_delete', false)) {
            return $query->withTrashed();
        }

        return $query;
    }
}

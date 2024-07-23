<?php

namespace Winter\Search\Engines;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine as BaseDatabaseEngine;
use Winter\Storm\Database\Traits\SoftDelete;
use Winter\Storm\Support\Arr;

class DatabaseEngine extends BaseDatabaseEngine
{
    /**
     * Ensure that soft delete handling is properly applied to the query.
     *
     * @param  \Winter\Search\Classes\Builder  $builder
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    protected function constrainForSoftDeletes($builder, $query)
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

    /**
     * Get the columns marked with a given attribute.
     *
     * Since Winter adds Scout capabilities through behaviours, we have no way to support the
     * attributes method of defining columns.
     *
     * @param  \Winter\Search\Classes\Builder  $builder
     * @param  string  $attributeClass
     * @return array
     */
    protected function getAttributeColumns(Builder $builder, $attributeClass)
    {
        return [];
    }

    /**
     * Get the full-text search options for the query.
     *
     * Since Winter adds Scout capabilities through behaviours, we have no way to support the
     * attributes method of defining columns.
     *
     * @param  \Winter\Search\Classes\Builder  $builder
     * @return array
     */
    protected function getFullTextOptions(Builder $builder)
    {
        return [];
    }
}

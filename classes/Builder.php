<?php

namespace Winter\Search\Classes;

use Laravel\Scout\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    public function getWithRelevance(?callable $relevanceCalculator = null)
    {
        $collection = $this->engine()->get($this);

        $relevanceCalculator = $relevanceCalculator ?? \Closure::fromCallable([$this, 'relevanceCalculator']);

        return $collection->map(function ($model) use ($relevanceCalculator) {
            $model->relevance = $relevanceCalculator($model, $this->query);
            return $model;
        })->sortByDesc('relevance');
    }

    public function firstWithRelevance(?callable $relevanceCalculator = null)
    {
        $collection = $this->engine()->get($this);

        $relevanceCalculator = $relevanceCalculator ?? \Closure::fromCallable([$this, 'relevanceCalculator']);

        return $collection->map(function ($model) use ($relevanceCalculator) {
            $model->relevance = $relevanceCalculator($model, $this->query);
            return $model;
        })->sortByDesc('relevance')->first();
    }

    /**
     * Calculates the relevance of a model to a query.
     *
     * @param \Winter\Storm\Database\Model|\Winter\Storm\Halcyon\Model $model
     * @param string $query
     * @return float|int
     */
    public function relevanceCalculator($model, $query)
    {
        // Get ranking map
        $rankingMap = $this->getRankingMap($model);

        $relevance = 0;

        foreach ($rankingMap as $field => $rank) {
            if (stripos($model->{$field}, $query) !== false) {
                // Count matches and multiply by rank
                $relevance += substr_count(strtolower($model->{$field}), strtolower($query)) * $rank;
            }
        }

        return $relevance;
    }

    /**
     * Gets a ranking map of the searchable fields.
     *
     * Searchable fields are ordered by descending importance, with the most important field first. It applies ranking
     * based on a double sequence.
     *
     * If no searchable fields are provided, this will return `false`.
     *
     * @return int[]|false
     */
    protected function getRankingMap($model)
    {
        if (!$model->propertyExists('searchable')) {
            return false;
        }

        $searchable = array_reverse($model->searchable);
        $rankingMap = [];
        $rank = 1;

        foreach ($searchable as $field) {
            $rankingMap[$field] = $rank;
            $rank *= 2;
        }

        return array_reverse($rankingMap, true);
    }
}

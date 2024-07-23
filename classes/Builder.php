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
            $model->relevance = $relevanceCalculator($model, $this->wordifyQuery($this->query));
            return $model;
        })->sortByDesc('relevance');
    }

    public function firstRelevant(?callable $relevanceCalculator = null)
    {
        $collection = $this->engine()->get($this);

        $relevanceCalculator = $relevanceCalculator ?? \Closure::fromCallable([$this, 'relevanceCalculator']);

        return $collection->map(function ($model) use ($relevanceCalculator) {
            $model->relevance = $relevanceCalculator($model, $this->wordifyQuery($this->query));
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
    public function relevanceCalculator($model, array $queryWords)
    {
        // Get ranking map
        $rankingMap = $this->getRankingMap($model);

        $relevance = 0;
        $multiplier = 2;

        // Go through and find each word in the searchable fields, with the first word being the most important, and
        // each word thereafter being less important
        foreach ($rankingMap as $field => $rank) {
            foreach ($queryWords as $query) {
                $multiplier /= 2;

                if (stripos($model->{$field}, $query) !== false) {
                    // Count matches and multiply by rank
                    $relevance += (
                        (substr_count(strtolower($model->{$field}), strtolower($query)) * $rank)
                        * $multiplier
                    );
                }
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

    /**
     * Convert a query string into an array of applicable words.
     *
     * This will strip all stop words and punctuation from the query string, then split each word into an array.
     */
    protected function wordifyQuery($query): array
    {
        $query = preg_replace('/[% ]+/', ' ', strtolower($query));

        return array_map(function ($word) {
            return trim($word, ' .,');
        }, preg_split('/ +/', $query));
    }
}

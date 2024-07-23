<?php

namespace Winter\Search\Components;

use Cms\Classes\ComponentBase;
use Illuminate\Database\Eloquent\Model as DbModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use System\Classes\PluginManager;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;
use Winter\Search\Plugin;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Support\Facades\Validator;
use Winter\Storm\Halcyon\Model as HalcyonModel;

/**
 * Search component.
 *
 * Creates a search box and displays search results.
 *
 * @author Ben Thomson <git@alfreido.com>
 */
class Search extends ComponentBase
{
    /**
     * @inheritDoc
     */
    public function componentDetails()
    {
        return [
            'name' => Plugin::LANG . 'components.search.name',
            'description' => Plugin::LANG . 'components.search.description',
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineProperties()
    {
        return [
            'handler' => [
                'title' => Plugin::LANG . 'components.search.handler.title',
                'description' => Plugin::LANG . 'components.search.handler.description',
                'type' => 'set',
                'required' => true,
                'placeholder' => Lang::get(Plugin::LANG . 'components.search.handler.placeholder'),
            ],
            'fuzzySearch' => [
                'title' => Plugin::LANG . 'components.search.fuzzySearch.title',
                'description' => Plugin::LANG . 'components.search.fuzzySearch.description',
                'type' => 'checkbox',
                'default' => false,
            ],
            'orderByRelevance' => [
                'title' => Plugin::LANG . 'components.search.orderByRelevance.title',
                'description' => Plugin::LANG . 'components.search.orderByRelevance.description',
                'type' => 'checkbox',
                'default' => false,
            ],
            'showExcerpts' => [
                'title' => Plugin::LANG . 'components.search.showExcerpts.title',
                'description' => Plugin::LANG . 'components.search.showExcerpts.description',
                'type' => 'checkbox',
                'default' => true,
            ],
            'limit' => [
                'title' => Plugin::LANG . 'components.search.limit.title',
                'description' => Plugin::LANG . 'components.search.limit.description',
                'type' => 'string',
                'default' => 100,
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => Plugin::LANG . 'components.search.limit.validationMessage',
                'group' => Plugin::LANG . 'components.search.groups.pagination',
            ],
            'perPage' => [
                'title' => Plugin::LANG . 'components.search.perPage.title',
                'description' => Plugin::LANG . 'components.search.perPage.description',
                'type' => 'string',
                'default' => 20,
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => Plugin::LANG . 'components.search.limit.validationMessage',
                'group' => Plugin::LANG . 'components.search.groups.pagination',
            ],
            'grouping' => [
                'title' => Plugin::LANG . 'components.search.grouping.title',
                'description' => Plugin::LANG . 'components.search.grouping.description',
                'type' => 'checkbox',
                'default' => false,
                'group' => Plugin::LANG . 'components.search.groups.grouping',
            ],
            'perGroup' => [
                'title' => Plugin::LANG . 'components.search.perGroup.title',
                'description' => Plugin::LANG . 'components.search.perGroup.description',
                'type' => 'string',
                'default' => 5,
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => Plugin::LANG . 'components.search.perGroup.validationMessage',
                'group' => Plugin::LANG . 'components.search.groups.grouping',
            ],
        ];
    }

    /**
     * Gets all registered search handlers.
     *
     * Plugins may specify a handler in their `Plugin.php` by creating a
     *
     * @return void
     */
    public function getHandlerOptions()
    {
        /** @var PluginManager */
        $pluginManager = PluginManager::instance();
        $registeredHandlers = $pluginManager->getRegistrationMethodValues('registerSearchHandlers');
        $options = [];

        if (!count($registeredHandlers)) {
            return [];
        }

        foreach ($registeredHandlers as $pluginCode => $handlers) {
            foreach ($handlers as $name => $handler) {
                if (array_key_exists($name, $handler)) {
                    continue;
                }

                $validator = Validator::make($handler, [
                    'model' => 'required',
                    'record' => 'required'
                ], [
                    'model.required' => Lang::get(Plugin::LANG . 'validation.modelRequired', [
                        'plugin' => $pluginCode,
                        'name' => $name
                    ]),
                    'record.required' => Lang::get(Plugin::LANG . 'validation.recordRequired', [
                        'plugin' => $pluginCode,
                        'name' => $name
                    ]),
                ]);
                if ($validator->fails()) {
                    Log::error($validator->getMessageBag()->first());
                    continue;
                }

                $options[$name] = Lang::get($handler['name']);
            }
        }

        return $options;
    }

    public function getSelectedHandlers()
    {
        /** @var PluginManager */
        $pluginManager = PluginManager::instance();
        $registeredHandlers = $pluginManager->getRegistrationMethodValues('registerSearchHandlers');
        $selectedHandlers = [];

        $selected = $this->property('handler');

        foreach ($registeredHandlers as $pluginCode => $handlers) {
            foreach ($handlers as $name => $handler) {
                if (array_key_exists($name, $selected)) {
                    continue;
                }

                $validator = Validator::make($handler, [
                    'model' => 'required',
                    'record' => 'required'
                ], [
                    'model.required' => Lang::get(Plugin::LANG . 'validation.modelRequired', [
                        'plugin' => $pluginCode,
                        'name' => $name
                    ]),
                    'record.required' => Lang::get(Plugin::LANG . 'validation.recordRequired', [
                        'plugin' => $pluginCode,
                        'name' => $name
                    ]),
                ]);
                if ($validator->fails()) {
                    Log::error($validator->getMessageBag()->first());
                    continue;
                }

                if (in_array($name, $selected)) {
                    $selectedHandlers[$name] = $handler;
                }
            }
        }

        return $selectedHandlers;
    }

    public function onSearch()
    {
        $query = Request::post('query');
        $page = Request::post('page', 1);
        $handlerPage = Request::post('handler');

        $handlers = $this->getSelectedHandlers();

        if (!count($handlers) || empty($query)) {
            return [
                '#' . $this->getId('results') => $this->renderPartial('@no-query'),
                'results' => [],
                'count' => 0,
            ];
        }

        $handlerResults = [];
        $totalCount = 0;
        $totalTotal = 0;

        if ($this->property('fuzzySearch', false)) {
            $processedQuery = $this->processQuery($query);
            if (empty($processedQuery)) {
                return [
                    '#' . $this->getId('results') => $this->renderPartial('@no-query'),
                    'results' => [],
                    'count' => 0,
                ];
            }
        } else {
            $processedQuery = $query;
        }

        foreach ($handlers as $id => $handler) {
            $class = $handler['model'];
            if (is_string($class)) {
                $class = new $class;
            }
            if (!is_callable($class) && !$class instanceof DbModel && !$class instanceof HalcyonModel) {
                throw new ApplicationException(
                    sprintf('Model for handler "%s" must be a database or Halcyon model, or a callback', $id)
                );
            }

            if (is_callable($class)) {
                $search = $class()->doSearch($processedQuery);
            } else {
                $search = $class->doSearch($processedQuery);
            }

            if ($this->property('orderByRelevance', false)) {
                $results = $search->getWithRelevance();
            } else {
                $results = $search->get();
            }

            if ($results->count() === 0) {
                $handlerResults[$id] = [
                    'name' => e(Lang::get($handler['name'])),
                    'results' => [],
                    'count' => 0,
                    'total' => 0,
                    'pages' => 1,
                    'currentPage' => 1,
                    'from' => 0,
                    'to' => 0,
                ];
                continue;
            }

            if ($handlerPage !== $id) {
                $page = 1;
            }

            $results = $this->paginateResults($results, $page);

            foreach ($results as $result) {
                $processed = $this->processRecord($result, $query, $handler['record']);

                if ($processed === false) {
                    continue;
                }

                if (!isset($handlerResults[$id])) {
                    $handlerResults[$id] = [
                        'name' => e(Lang::get($handler['name'])),
                        'results' => [],
                        'count' => $results->count(),
                        'total' => $results->total(),
                        'pages' => $results->lastPage(),
                        'currentPage' => $page,
                        'from' => $results->firstItem(),
                        'to' => $results->lastItem(),
                    ];
                    $totalCount += $results->count();
                    $totalTotal += $results->total();
                }

                $handlerResults[$id]['results'][] = $processed;
            }

            if ($this->property('grouping', false)) {
                $handlerResults[$id]['results'] = $this->applyGrouping($handlerResults[$id]['results']);
            }
        }

        return [
            '#' . $this->getId('results') => ($totalCount === 0)
                ? $this->renderPartial('@no-results')
                : $this->renderPartial('@results', [
                    'selectedHandler' => $handlerPage ?? array_keys($handlers)[0],
                    'query' => $query,
                    'results' => $handlerResults,
                    'count' => $totalCount,
                    'total' => $totalTotal,
                ]),
            'selectedHandler' => $handlerPage ?? array_keys($handlers)[0],
            'query' => $query,
            'results' => $handlerResults,
            'count' => $totalCount,
            'total' => $totalTotal,
        ];
    }

    /**
     * Processes each record returned by the search handler.
     *
     * This method calls the search handler and allows the search handler to manage and format how the result appears
     * in the results list. Each result should be an array with at least the following information:
     *
     * - `title`: The title of the result.
     * - `description`: A brief description of the result.
     * - `url`: The URL to the result.
     *
     * In addition, you may provide these optional attributes:
     *
     * - `group`: The group to which the result belongs.
     * - `label`: A label to display for the result.
     * - `image`: An image to display next to the result.
     */
    protected function processRecord($record, string $query, array|callable $handler)
    {
        $requiredAttributes = ['title', 'description', 'url'];
        $optionalAttributes = ['group', 'label', 'image'];

        if (is_callable($handler)) {
            $processed = $handler($record, $query);

            if (!is_array($processed)) {
                return false;
            }

            foreach ($requiredAttributes as $attr) {
                if (!array_key_exists($attr, $processed)) {
                    return false;
                }
            }

            // Remove processed values that are not required or optional
            $processed = array_filter($processed, function ($key) use ($requiredAttributes, $optionalAttributes) {
                return in_array($key, array_merge($requiredAttributes, $optionalAttributes));
            }, ARRAY_FILTER_USE_KEY);

            return $processed;
        } else {
            $processed = [];

            foreach ($requiredAttributes as $attr) {
                if (!isset($handler[$attr])) {
                    return false;
                }
            }

            foreach ($handler as $attr => $value) {
                if (in_array($attr, array_merge($requiredAttributes, $optionalAttributes))) {
                    continue;
                }

                $processed[$attr] = $value;
            }

            return $processed;
        }
    }

    /**
     * Gets an alias-prefixed ID for partials.
     *
     * @param string $id
     * @return string
     */
    public function getId(string $id): string
    {
        return $this->alias . '-' . $id;
    }

    /**
     * Determines if results are being grouped.
     */
    public function isGrouped(): bool
    {
        return $this->property('grouping', false);
    }

    /**
     * Determines if excerpts should be shown.
     */
    public function showExcerpts(): bool
    {
        return $this->property('showExcerpts', true);
    }

    /**
     * Creates a paginator for search results.
     */
    protected function paginateResults(Collection $results, int $page = 1)
    {
        return new LengthAwarePaginator(
            $results,
            $results->count(),
            $this->property('perPage', 20),
            $page,
        );
    }

    /**
     * Applies grouping to results, if required.
     */
    protected function applyGrouping(array $results)
    {
        $grouped = [];

        foreach ($results as $result) {
            $group = $result['group'] ?? 'Other results';

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            if (count($grouped[$group]) >= $this->property('perGroup', 5)) {
                continue;
            }

            $grouped[$group][] = $result;
        }

        return $grouped;
    }

    /**
     * Processes the search query.
     *
     * This applies some processing of the search query to get better search results. It does the following:
     *
     * - Removes any percentage signs from the query, in order to not get full wildcard searches.
     * - Strips punctuation from the query.
     * - Removes any stop words.
     * - Stems each word in the query, so words with the incorrect inflection or pluralisation can still be found.
     * - Replaces spacing with percentages, in order to allow words that aren't next to each other to be found.
     * - Trims the query.
     */
    protected function processQuery(string $query): string
    {
        $query = str_replace('%', '', strtolower($query));
        $words = preg_split('/[ -]+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        // Strip punctuation
        $words = array_map(function ($word) {
            return preg_replace('/[^a-z0-9]/', '', $word);
        }, $words);

        // Remove stop words
        $words = array_filter($words, function ($word) {
            return $this->isStopWord($word);
        });

        // Stem words
        $words = array_map(function ($word) {
            return PorterStemmer::stem($word);
        }, $words);

        // Replace spaces with wildcards to match partial words and trim query
        return trim(implode('% %', $words));
    }

    protected function isStopWord(string $word): bool
    {
        return !in_array(strtolower($word), [
            'i',
            'me',
            'my',
            'myself',
            'we',
            'our',
            'ours',
            'ourselves',
            'you',
            'your',
            'yours',
            'yourself',
            'yourselves',
            'he',
            'him',
            'his',
            'himself',
            'she',
            'her',
            'hers',
            'herself',
            'it',
            'its',
            'itself',
            'they',
            'them',
            'their',
            'theirs',
            'themselves',
            'what',
            'which',
            'who',
            'whom',
            'this',
            'that',
            'these',
            'those',
            'am',
            'is',
            'are',
            'was',
            'were',
            'be',
            'been',
            'being',
            'have',
            'has',
            'had',
            'having',
            'do',
            'does',
            'did',
            'doing',
            'a',
            'an',
            'the',
            'and',
            'but',
            'if',
            'or',
            'because',
            'as',
            'until',
            'while',
            'of',
            'at',
            'by',
            'for',
            'with',
            'about',
            'against',
            'between',
            'into',
            'through',
            'during',
            'before',
            'after',
            'above',
            'below',
            'to',
            'from',
            'up',
            'down',
            'in',
            'out',
            'on',
            'off',
            'over',
            'under',
            'again',
            'further',
            'then',
            'once',
            'here',
            'there',
            'when',
            'where',
            'why',
            'how',
            'all',
            'any',
            'both',
            'each',
            'few',
            'more',
            'most',
            'other',
            'some',
            'such',
            'no',
            'nor',
            'not',
            'only',
            'own',
            'same',
            'so',
            'than',
            'too',
            'very',
            's',
            't',
            'can',
            'will',
            'just',
            'don',
            'should',
            'now',
        ]);
    }
}

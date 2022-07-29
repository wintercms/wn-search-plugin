<?php

namespace Winter\Search\Components;

use Lang;
use Winter\Search\Plugin;
use Cms\Classes\ComponentBase;
use Illuminate\Database\Eloquent\Model as DbModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use System\Classes\PluginManager;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Support\Arr;
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
            ]
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
                $results = $class()->doSearch($query)->paginate($this->property('perPage', 20), 'page', ($handlerPage === $id) ? $page : 1);
            } else {
                $results = $class->doSearch($query)->paginate($this->property('perPage', 20), 'page', ($handlerPage === $id) ? $page : 1);
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

    protected function processRecord($record, string $query, array|callable $handler)
    {
        $requiredAttributes = ['title', 'description', 'url'];

        if (is_callable($handler)) {
            $processed = $handler($record, $query);

            foreach ($requiredAttributes as $attr) {
                if (!array_key_exists($attr, $processed)) {
                    return false;
                }
            }

            return $processed;
        } else {
            $processed = [];

            foreach ($requiredAttributes as $attr) {
                if (!isset($handler[$attr])) {
                    return false;
                }

                $processed[$attr] = Arr::get($record, $handler[$attr], null);
            }

            foreach ($handler as $attr => $value) {
                if (in_array($attr, $requiredAttributes)) {
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
}

<?php

namespace Winter\Search\Components;

use Lang;
use Winter\Search\Plugin;
use Cms\Classes\ComponentBase;
use System\Classes\PluginManager;

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
            ],
            'combineResults' => [
                'title' => Plugin::LANG . 'components.search.combineResults.title',
                'description' => Plugin::LANG . 'components.search.combineResults.description',
                'type' => 'checkbox',
                'default' => false,
                'group' => Plugin::LANG . 'components.search.groups.pagination',
            ],
            'displayImages' => [
                'title' => Plugin::LANG . 'components.search.displayImages.title',
                'type' => 'checkbox',
                'default' => true,
                'group' => Plugin::LANG . 'components.search.groups.display',
                'showExternalParam' => false,
            ],
            'displayHandlerName' => [
                'title' => Plugin::LANG . 'components.search.displayHandlerName.title',
                'description' => Plugin::LANG . 'components.search.displayHandlerName.description',
                'type' => 'checkbox',
                'default' => true,
                'group' => Plugin::LANG . 'components.search.groups.display',
                'showExternalParam' => false,
            ],
            'displayPluginName' => [
                'title' => Plugin::LANG . 'components.search.displayPluginName.title',
                'description' => Plugin::LANG . 'components.search.displayPluginName.description',
                'type' => 'checkbox',
                'default' => false,
                'group' => Plugin::LANG . 'components.search.groups.display',
                'showExternalParam' => false,
            ]
        ];
    }

    public function getHandlerOptions()
    {
        /** @var PluginManager */
        $pluginManager = PluginManager::instance();
        $registeredHandlers = $pluginManager->getRegistrationMethodValues('registerSearchHandlers');
        $handlers = [];

        if (!count($registeredHandlers)) {
            return [];
        }

        foreach (array_values($registeredHandlers) as $handlers) {
            foreach ($handlers as $name => $handler) {
                if (array_key_exists($name, $handler)) {
                    continue;
                }

                $handlers[$name] = Lang::get($handler['name']);
            }
        }

        return $handlers;
    }

    public function onSearch()
    {
        return [
            'results' => [],
        ];
    }
}

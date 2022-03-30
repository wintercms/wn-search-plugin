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

        foreach ($registeredHandlers as $pluginCode => $handlers) {
            foreach ($handlers as $name => $handler) {
                if (array_key_exists($name, $handler)) {
                    continue;
                }

                $handlers[$name] = Lang::get($handler['name']);
            }
        }

        return $handlers;
    }
}

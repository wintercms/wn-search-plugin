<?php namespace Winter\Search\Classes;

use Config;
use System\Classes\PluginManager;
use Winter\Search\Behaviors\Halcyon\Searchable as HalcyonSearchable;
use Winter\Search\Behaviors\Searchable;
use Winter\Storm\Support\Traits\Singleton;

class CorePluginManager
{
    use Singleton;

    /**
     * Supported first-class plugins.
     */
    protected array $plugins = [];

    /**
     * Initialisation.
     *
     * Defines the currently supported first-party plugins, their searchable data, and the records
     * to return.
     *
     * @return void
     */
    protected function init()
    {
        $this->plugins = [
            'cmsPages' => [
                'type' => 'module',
                'code' => 'Cms',
                'name' => 'winter.search::lang.otherPlugins.cmsPages',
                'behavior' => HalcyonSearchable::class,
                'model' => \Cms\Classes\Page::class,
                'searchable' => function ($model) {
                    return [
                        'title' => $model->title,
                        'description' => $model->description ?? '',
                        'meta_title' => $model->meta_title ?? '',
                        'meta_description' => $model->meta_description ?? '',
                    ];
                },
                'record' => function ($model) {
                    return [
                        'title' => $model->title,
                        'description' => $model->description,
                        'image' => null,
                        'url' => $model->url,
                    ];
                },
            ],
            'staticPages' => [
                'type' => 'plugin',
                'code' => 'Winter.Pages',
                'name' => 'winter.search::lang.otherPlugins.staticPages',
                'behavior' => HalcyonSearchable::class,
                'model' => \Winter\Pages\Classes\Page::class,
                'searchable' => function ($model) {
                    return [
                        'title' => $model->getViewBag()->title,
                        'meta_title' => $model->getViewBag()->meta_title ?? '',
                        'meta_description' => $model->getViewBag()->meta_description ?? '',
                    ];
                },
                'record' => function ($model) {
                    return [
                        'title' => $model->getViewBag()->title,
                        'description' => $model->getViewBag()->meta_description,
                        'image' => null,
                        'url' => $model->getViewBag()->url,
                    ];
                },
            ],
            'winterBlog' => [
                'type' => 'plugin',
                'code' => 'Winter.Blog',
                'name' => 'winter.search::lang.otherPlugins.winterBlog',
                'behavior' => Searchable::class,
                'model' => \Winter\Blog\Models\Post::class,
                'searchable' => function ($model) {
                    return [
                        'title' => $model->title,
                        'content' => $model->content,
                        'excerpt' => $model->excerpt,
                    ];
                },
                'record' => function ($model) {
                    if (!$model->published) {
                        return;
                    }

                    return [
                        'title' => $model->title,
                        'description' => $model->excerpt,
                        'image' => (count($model->featured_images)) ? $model->featured_images[0] : null,
                        'url' => $model->slug,
                    ];
                },
            ],
        ];
    }

    public function attachCorePlugins()
    {
        foreach ($this->plugins as $id => $config) {
            // Skip disabled plugins from search config
            if (Config::get('search.plugins.' . $id, true) === false) {
                continue;
            }
            // Skip disabled modules or plugins
            if (!$this->isEnabled($config['type'], $config['code'])) {
                return;
            }

            $config['model']::extend(function ($model) use ($config) {
                if (
                    $model->isClassExtendedWith(Searchable::class)
                    || $model->isClassExtendedWith(HalcyonSearchable::class)
                ) {
                    return;
                }

                $model->extendClassWith($config['behavior']);

                $model->addDynamicMethod('getSearchableArray', function () use ($model, $config) {
                    return $config['searchable']($model);
                });
            });
        }
    }

    public function getHandlers()
    {
        $handlers = [];

        foreach ($this->plugins as $id => $config) {
            // Skip disabled plugins from search config
            if (Config::get('search.plugins.' . $id, true) === false) {
                continue;
            }
            $handlers[$id] = [
                'name' => e(trans($config['name'])),
                'model' => $config['model'],
                'record' => $config['record'] ?? [
                    'title' => 'title',
                    'description' => 'description',
                    'url' => 'url',
                ]
            ];
        }

        return $handlers;
    }

    protected function isEnabled(string $type, string $code)
    {
        if ($type === 'module') {
            $modules = Config::get('cms.loadModules', ['System', 'Backend', 'Cms']);
            return in_array($code, $modules);
        }

        if ($type === 'plugin') {
            return PluginManager::instance()->exists($code);
        }
    }
}

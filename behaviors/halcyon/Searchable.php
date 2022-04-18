<?php

namespace Winter\Search\Behaviors\Halcyon;

use Cms\Classes\Theme;
use Laravel\Scout\Builder;
use Winter\Search\Behaviors\Searchable as BaseSearchable;
use Winter\Search\Classes\HalcyonModelObserver;
use Winter\Storm\Support\Arr;
use Winter\Storm\Support\Str;
use Winter\Storm\Support\Facades\Config;

class Searchable extends BaseSearchable
{
    /**
     * @var \Winter\Storm\Halcyon\Model $model The model instance being extended
     */
    protected $model;

    /**
     * @var string[] Classes that have been booted with this behaviour.
     */
    public static $bootedClasses = [];

    /**
     * Constructor for the behaviour.
     *
     * Attaches listeners to the model.
     *
     * @param \Winter\Storm\Halcyon\Model $model
     */
    public function __construct($model)
    {
        $this->model = $model;
        static::$extendableStaticCalledClass = get_class($this->model);

        if (!in_array(static::getCalledExtensionClass(), static::$bootedClasses)) {
            $this->bootSearchable();
            static::$booted = true;
        }
        if (!static::$booted) {
            $this->registerSearchableMacros();
        }
    }

        /**
     * Boot the trait.
     *
     * @return void
     */
    protected function bootSearchable()
    {
        $class = static::getCalledExtensionClass();
        static::$bootedClasses[] = $class;
        new HalcyonModelObserver(new $class);
    }

    /**
     * Make all instances of the model searchable.
     *
     * @param  int  $chunk
     * @return void
     */
    public static function makeAllSearchable($chunk = null)
    {
        $model = static::getCalledExtensionClass();
        $self = new $model;

        $self->newQuery()
            ->get()
            ->searchable($chunk);
    }

    /**
     * Get a query builder for retrieving the requested models from an array of object IDs.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $ids
     * @return mixed
     */
    public function queryScoutModelsByIds(Builder $builder, array $ids)
    {
        $query = $this->model->newQuery();
        $results = $query->get()->map(function ($item) {
            $item->fileName = Str::slug(str_replace('.', '-', $item->fileName));
            return $item;
        })->toArray();

        $test = $query->get()->map(function ($item) {
            $item->fileName = Str::slug(str_replace('.', '-', $item->fileName));
            return $item;
        })->whereIn(
            $this->getScoutKeyName(),
            $ids
        );
    }

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        $themeCode = Theme::getActiveThemeCode();
        $dirName = $this->model->getObjectTypeDirName();

        return Config::get('search.prefix') . Str::slug($themeCode . '-' . str_replace(['/', '\\'], '-', $dirName));
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        if ($this->model->propertyExists('searchable')) {
            $searchableData = [];
            $modelAttributes = Arr::dot($this->model->getAttributes());

            foreach ($this->model->searchable as $attribute) {
                // Convert filenames so they don't fail the ID checks of some engines
                if ($attribute === 'fileName') {
                    $searchableData[$attribute] = Str::slug(str_replace('.', '-', $this->model->getFileName()));
                    continue;
                }

                // Convert to dot notation
                $attribute = str_replace(['[', ']'], ['.', ''], $attribute);
                Arr::set($searchableData, $attribute, $modelAttributes[$attribute] ?? null);
            }

            return $searchableData;
        }

        $attributes = $this->model->toArray();
        // Convert filenames so they don't fail the ID checks of some engines
        $attributes['fileName'] = Str::slug(str_replace('.', '-', $this->model->getFileName()));

        return $attributes;
    }

    /**
     * Get the value used to index the model.
     *
     * @return mixed
     */
    public function getKeyName()
    {
        return 'fileName';
    }

    /**
     * Get the value used to index the model.
     *
     * @return mixed
     */
    public function getSearchKey()
    {
        return Str::slug(str_replace('.', '-', $this->model->getFileName()));
    }

    /**
     * Get the key name used to index the model.
     *
     * @return mixed
     */
    public function getSearchKeyName()
    {
        return 'fileName';
    }
}

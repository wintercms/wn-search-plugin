<?php

namespace Winter\Search\Behaviors\Halcyon;

use Cms\Classes\Theme;
use Winter\Search\Classes\Builder;
use Illuminate\Support\Collection as BaseCollection;
use Winter\Search\Behaviors\Searchable as BaseSearchable;
use Winter\Search\Classes\HalcyonIndex;
use Winter\Search\Classes\HalcyonModelObserver;
use Winter\Storm\Support\Arr;
use Winter\Storm\Support\Str;
use Winter\Storm\Support\Facades\Config;

class Searchable extends BaseSearchable
{
    /**
     * @var \Winter\Storm\Halcyon\Model $model The model instance being extended.
     */
    protected $baseModel;

    /**
     * @var array<string, \Winter\Search\Classes\HalcyonIndex> Index models created for individual Halcyon model types.
     */
    public static $indexProxies = [];

    /**
     * @var boolean Whether the global search functionality has been booted.
     */
    public static $booted = false;

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
        $this->baseModel = $model;
        $this->model = $this->getIndexModel($model);

        if (!in_array(get_class($this->baseModel), static::$bootedClasses)) {
            $this->bootSearchable();
        }

        if (!static::$booted) {
            $this->registerSearchableMacros();
            static::$booted = true;
        }
    }

    /**
     * Retrieves the index proxy for the given Halcyon model, creating one if it doesn't exist.
     */
    public function getIndexModel(\Winter\Storm\Halcyon\Model|string $model): HalcyonIndex
    {
        if (array_key_exists(get_class($this->baseModel), static::$indexProxies)) {
            return static::$indexProxies[get_class($this->baseModel)];
        }

        HalcyonIndex::setModel($model);
        HalcyonIndex::needsUpdate();

        $index = new HalcyonIndex;

        // Halcyon index will double-boot this behaviour when getting records, so we'll prevent
        // it from double-booting the index itself.
        if (!array_key_exists(get_class($this->baseModel), static::$indexProxies)) {
            $index->setIdentifier();
            static::$indexProxies[get_class($this->baseModel)] = $index;
        }

        HalcyonIndex::setModel(null);

        return $index;
    }

    /**
     * Boot the trait.
     *
     * @return void
     */
    protected function bootSearchable()
    {
        $class = get_class($this->baseModel);
        static::$bootedClasses[] = $class;
        new HalcyonModelObserver(new $class);
    }

    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search($query = '', $callback = null)
    {
        $model = static::getCalledExtensionClass();
        HalcyonIndex::setModel($model);

        return app(Builder::class, [
            'model' => new $model,
            'query' => $query,
            'callback' => $callback,
            'softDelete'=> static::usesSoftDelete() && Config::get('search.soft_delete', false),
        ]);
    }

    /**
     * Perform a search against the model's indexed data.
     *
     * This is the same as the static::search() method, except that it can run on an instance of the model.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public function doSearch($query = '', $callback = null)
    {
        HalcyonIndex::setModel($this->baseModel);

        return app(Builder::class, [
            'model' => $this->model,
            'query' => $query,
            'callback' => $callback,
            'softDelete'=> static::usesSoftDelete() && Config::get('search.soft_delete', false),
        ]);
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
     * Get the requested models from an array of object IDs.
     *
     * @param  \Winter\Search\Classes\Builder  $builder
     * @param  array  $ids
     * @return mixed
     */
    public function getScoutModelsByIds(Builder $builder, array $ids)
    {
        return $this->queryScoutModelsByIds($builder, $ids);
    }

    /**
     * Get a query builder for retrieving the requested models from an array of object IDs.
     *
     * @param  \Winter\Search\Classes\Builder  $builder
     * @param  array  $ids
     * @return mixed
     */
    public function queryScoutModelsByIds(Builder $builder, array $ids)
    {
        $query = $this->model->newQuery();

        $records = array_flip(array_map(function ($fileName) {
            return Str::slug(str_replace('.', '-', $fileName));
        }, $query->lists('fileName', 'fileName')));

        // Filter records
        $records = array_filter($records, function ($key) use ($ids) {
            return in_array($key, $ids);
        }, ARRAY_FILTER_USE_KEY);

        // Create array of models
        $models = [];
        foreach (array_values($records) as $fileName) {
            $model = $this->model->newQuery()->find($fileName);
            if ($model) {
                $models[] = $model;
            }
        }

        return new BaseCollection($models);
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
        if ($this->model->methodExists('getSearchableArray')) {
            $attributes = $this->model->getSearchableArray();

            // Convert filenames so they don't fail the ID checks of some engines
            $attributes['fileName'] = Str::slug(str_replace('.', '-', $this->model->getFileName()));

            return $attributes;
        } elseif ($this->model->propertyExists('searchable')) {
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

            if (!array_key_exists('fileName', $searchableData)) {
                // Convert filenames so they don't fail the ID checks of some engines
                $searchableData['fileName'] = Str::slug(str_replace('.', '-', $this->model->getFileName()));
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

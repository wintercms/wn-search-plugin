<?php

namespace Winter\Search\Traits\Halcyon;

use Str;
use Config;
use Cms\Classes\Theme;
use Winter\Search\Classes\HalcyonModelObserver;
use Winter\Search\Traits\Searchable as BaseSearchable;

trait Searchable
{
    use BaseSearchable;

    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootSearchable()
    {
        new HalcyonModelObserver(new static);

        (new static)->registerSearchableMacros();
    }

    /**
     * Make all instances of the model searchable.
     *
     * @param  int  $chunk
     * @return void
     */
    public static function makeAllSearchable($chunk = null)
    {
        $self = new static;

        $softDelete = static::usesSoftDelete() && Config::get('search.soft_delete', false);

        $self->newQuery()
            ->get()
            ->searchable($chunk);
    }

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        $themeCode = Theme::getActiveThemeCode();
        $dirName = $this->getObjectTypeDirName();

        return Config::get('search.prefix') . Str::slug($themeCode . '-' . str_replace(['/', '\\'], '-', $dirName));
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        if (property_exists($this, 'searchable')) {
            $searchableData = [];
            $modelAttributes = Arr::dot($this->getAttributes());

            foreach ($this->searchable as $attribute) {
                // Convert filenames so they don't fail the ID checks of some engines
                if ($attribute === 'fileName') {
                    $searchableData[$attribute] = Str::slug(str_replace('.', '-', $this->getFileName()));
                    continue;
                }

                // Convert to dot notation
                $attribute = str_replace(['[', ']'], ['.', ''], $attribute);
                Arr::set($searchableData, $attribute, $modelAttributes[$attribute] ?? null);
            }

            return $searchableData;
        }

        $attributes = $this->toArray();
        // Convert filenames so they don't fail the ID checks of some engines
        $attributes['fileName'] = Str::slug(str_replace('.', '-', $this->getFileName()));

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
        return Str::slug(str_replace('.', '-', $this->getFileName()));
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

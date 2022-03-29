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
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        $themeCode = Theme::getActiveThemeCode();
        $dirName = $this->getObjectTypeDirName();

        return Config::get('search.prefix') . Str::slug($themeCode . '-' . $dirName);
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

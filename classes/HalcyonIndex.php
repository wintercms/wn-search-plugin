<?php

namespace Winter\Search\Classes;

use Closure;
use Cms\Classes\Theme;
use Winter\Storm\Database\Model;
use Winter\Storm\Database\Traits\ArraySource;
use Winter\Storm\Database\Traits\Purgeable;
use Winter\Storm\Halcyon\Model as HalcyonModel;
use Winter\Storm\Support\Str;

/**
 * Halcyon index model.
 *
 * This is a proxy model for Halcyon records that collates all available pages as an array and then
 * executes any required indexing and search operations using the Laravel's standard database API.
 *
 * This gets us around the fact that Halcyon doesn't use the database API, and therefore isn't
 * compatible with Scout.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright 2023 Winter CMS.
 */
class HalcyonIndex extends Model
{
    use ArraySource;
    use Purgeable;

    public $implement = [
        '@Winter.Search.Behaviors.Searchable',
    ];

    protected $primaryKey = 'fileName';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Purgeable attributes
     *
     * @var array
     */
    public $purgeable = [
        'baseModel'
    ];

    public $fillable = [
        'slug',
        'path',
        'title',
        'content',
    ];

    public $recordSchema = [
        'filename' => 'string',
        'title' => 'string',
        'content' => 'text',
    ];

    public $searchable = [
        'filename',
        'title',
        'content',
    ];

    /**
     * Base Halcyon model.
     */
    protected static ?HalcyonModel $baseModel = null;

    /**
     * Determines if the index needs to be updated.
     */
    protected static bool $needsUpdate = false;

    /**
     * Sets the base Halcyon model to index and search.
     */
    public static function setModel(?HalcyonModel $baseModel): void
    {
        static::$baseModel = $baseModel;
    }

    /**
     * Tells the Array Source trait that the index needs updating.
     */
    public static function needsUpdate()
    {
        static::$needsUpdate = true;
    }

    /**
     * Sets the name of the search index. This is based off the docs name.
     *
     * @return void
     */
    public function searchableAs()
    {
        return Str::slug(str_replace('.', '-', 'halcyon-' . $this->getModelIdentifier()));
    }

    protected function getModelIdentifier()
    {
        $theme = Theme::getActiveThemeCode();
        return $theme . '-' . get_class(static::$baseModel);
    }

    /**
     * Make search index searchable by the slug.
     *
     * @return string
     */
    public function getSearchKey()
    {
        return 'fileName';
    }

    public function getRecords(): array
    {
        if (is_null(static::$baseModel)) {
            return [];
        }
        
        $className = get_class(static::$baseModel);
        $closure = Closure::fromCallable([$className, 'listInTheme']);
        $records = [];

        $closure(Theme::getActiveTheme(), true)->each(function ($item) {
            print_r($item);
            die();
        });

        return $records;
    }

    public function index()
    {

    }

    /**
     * Determines if the stored array DB should be updated.
     */
    protected function arraySourceDbNeedsUpdate(): bool
    {
        if (static::$needsUpdate) {
            return true;
        }

        if (!$this->arraySourceCanStoreDb()) {
            return true;
        }

        if (!File::exists($this->arraySourceGetDbPath())) {
            return true;
        }

        $modelFile = (new \ReflectionClass(static::class))->getFileName();

        if (File::lastModified($this->arraySourceGetDbPath()) < File::lastModified($modelFile)) {
            return true;
        }

        return false;
    }

    /**
     * Gets the path where the array database will be stored.
     */
    protected function arraySourceGetDbPath(): string
    {
        $class = str_replace('\\', '', static::class);
        return $this->arraySourceGetDbDir() . '/' . $this->searchableAs() . '.sqlite';
    }
}

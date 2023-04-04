<?php

namespace Winter\Search\Classes;

use Closure;
use Cms\Classes\Theme;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Winter\Storm\Database\Connectors\ConnectionFactory;
use Winter\Storm\Database\Model;
use Winter\Storm\Database\Traits\ArraySource;
use Winter\Storm\Database\Traits\Purgeable;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Halcyon\Collection;
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
        'Winter.Search.Behaviors.Searchable',
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
     * Identifier used after initialisation.
     */
    protected ?string $identifier = null;

    /**
     * Determines if the index needs to be updated.
     */
    protected static bool $needsUpdate = false;

    /**
     * Connections to the SQLite datasource for each index.
     */
    public static array $connections = [];

    /**
     * Boots the ArraySource trait.
     */
    public static function bootArraySource(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            throw new ApplicationException('You must enable the SQLite PDO driver to use the ArraySource trait');
        }
    }

    /**
     * Sets the base Halcyon model to index and search.
     */
    public static function setModel(HalcyonModel|string|null $baseModel): void
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
     * Sets the identifier during initialisation.
     */
    public function setIdentifier(?string $identifier = null): void
    {
        $this->identifier = $identifier ?? $this->getModelIdentifier();
    }

    /**
     * Sets the name of the search index. This is based off the docs name.
     *
     * @return void
     */
    public function searchableAs()
    {
        return 'halcyon-' . $this->getModelIdentifier();
    }

    /**
     * Gets a unique identifier for a given Halcyon model, to use to define connections and the database.
     */
    protected function getModelIdentifier(): string
    {
        if (!is_null($this->identifier)) {
            return $this->identifier;
        }

        $theme = Theme::getActiveThemeCode();
        return Str::slug(str_replace(['.', '\\'], '-', $theme . '-' . $this->getBaseModelClass()));
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

    /**
     * Gets the available Halcyon model records.
     */
    public function getRecords(): array
    {
        if (is_null(static::$baseModel)) {
            return [];
        }

        $className = $this->getBaseModelClass();
        $closure = Closure::fromCallable([$className, 'listInTheme']);
        $records = [];

        $closure(Theme::getActiveTheme(), true)->each(function ($item) use (&$records) {
            $records[] = [
                'fileName' => $item->fileName,
                'title' => $item->title,
                'content' => $item->content,
            ];
        });

        return $records;
    }

    /**
     * Indexes the available Halcyon model records.
     */
    public function index(): void
    {
        static::all()->each(function ($item) {
            $item->save();
        });
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
        return $this->arraySourceGetDbDir() . '/' . $this->searchableAs() . '.sqlite';
    }

    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        if (!array_key_exists($this->getModelIdentifier(), static::$connections)) {
            $config = [
                'driver' => 'sqlite',
                'database' => (!$this->arraySourceCanStoreDb())
                    ? ':memory:'
                    : $this->arraySourceGetDbPath()
            ];

            static::$connections[$this->getModelIdentifier()] = App::get(ConnectionFactory::class)->make($config);

            if ($this->arraySourceDbNeedsUpdate()) {
                $this->arraySourceCreateDb();
            }
        }

        return static::$connections[$this->getModelIdentifier()];
    }

    public static function resolveConnection($connection = null)
    {
        return null;
    }

    /**
     * Gets the class name of the base Halcyon model that's being indexed or queried.
     */
    protected function getBaseModelClass(): ?string
    {
        if (is_object(static::$baseModel)) {
            return get_class(static::$baseModel);
        } elseif (is_string(static::$baseModel)) {
            return static::$baseModel;
        }

        return null;
    }

    /**
     * Populates a new collection of models.
     *
     * This swaps out this index model with the base Halcyon model records.
     *
     * @param static[] $models
     */
    public function newCollection(array $models = []): Collection
    {
        // Swap out the base model for the Halcyon model.
        $className = $this->getBaseModelClass();
        if (is_null($className)) {
            return new Collection();
        }

        $closure = Closure::fromCallable([$className, 'load']);
        $theme = Theme::getActiveTheme();

        $collection = new Collection(array_map(function ($item) use ($closure, $theme) {
            return $closure($theme, $item->fileName);
        }, $models));

        return $collection->filter();
    }

    /**
     * Creates the temporary SQLite table.
     */
    protected function arraySourceCreateTable(): void
    {
        $builder = $this->getConnection()->getSchemaBuilder();

        try {
            $builder->create($this->getTable(), function ($table) {
                // Allow for overwriting schema types via the $recordSchema property
                $schema = ($this->propertyExists('recordSchema'))
                    ? $this->recordSchema
                    : [];
                $firstRecord = $this->getRecords()[0] ?? [];

                if (empty($schema) && empty($firstRecord)) {
                    throw new ApplicationException(
                        'A model using the ArraySource trait must either provide "$records" or "$recordSchema" as an array.'
                    );
                }

                // Add incrementing field based on the primary key if the key is not found in the first record or schema
                if (
                    $this->incrementing
                    && !array_key_exists($this->primaryKey, $schema)
                    && !array_key_exists($this->primaryKey, $firstRecord)
                ) {
                    $table->increments($this->primaryKey);
                }

                if (!empty($firstRecord)) {
                    foreach ($firstRecord as $column => $value) {
                        $type = $this->arraySourceResolveDatatype($value);

                        // Ensure the primary key is correctly created as an autoincremeting integer
                        if ($column === $this->primaryKey && $type === 'integer') {
                            $table->increments($this->primaryKey);
                            continue;
                        }

                        $type = $schema[$column] ?? $type;

                        $table->$type($column)->nullable();
                    }

                    // Create timestamp columns if they are not explicitly set in the first record
                    if (
                        $this->usesTimestamps()
                        && (
                            !in_array('created_at', array_keys($firstRecord))
                            || !in_array('updated_at', array_keys($firstRecord))
                        )
                    ) {
                        $table->timestamps();
                    }
                } else {
                    foreach ($schema as $column => $type) {
                        // Ensure the primary key is correctly created as an autoincremeting integer
                        if ($column === $this->primaryKey && $type === 'integer') {
                            $table->increments($this->primaryKey);
                            continue;
                        }

                        $table->$type($column)->nullable();
                    }

                    // Create timestamp columns if required
                    if ($this->usesTimestamps()) {
                        $table->timestamps();
                    }
                }
            });
        } catch (QueryException $e) {
            if (Str::contains($e->getMessage(), 'already exists (SQL: create table', true)) {
                // Prevents race conditions on creating the table
                return;
            }

            throw $e;
        }
    }
}

<?php

namespace Winter\Search\Tests\Fixtures;

use Model;
use Winter\Search\Behaviors\Searchable;
use Winter\Storm\Database\Traits\SoftDelete;

class SearchableModelWithSensitiveAttributes extends Model
{
    use SoftDelete;

    public $implement = [
        Searchable::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['first_name', 'last_name', 'remember_token', 'password'];

    /**
     * When updating a model, this method determines if we
     * should perform a search engine update or not.
     *
     * @return bool
     */
    public function searchIndexShouldBeUpdated(): bool
    {
        $sensitiveAttributeKeys = ['first_name', 'last_name'];

        return collect($this->getDirty())->keys()
            ->intersect($sensitiveAttributeKeys)
            ->isNotEmpty();
    }
}

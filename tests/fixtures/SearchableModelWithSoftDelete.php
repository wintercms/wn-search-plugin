<?php

namespace Winter\Search\Tests\Fixtures;

use Model;
use Winter\Search\Behaviors\Searchable;
use Winter\Storm\Database\Traits\SoftDelete;

class SearchableModelWithSoftDelete extends Model
{
    use SoftDelete;

    public $implement = [
        Searchable::class
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['published_at'];

    public function shouldBeSearchable()
    {
        return !$this->trashed() && !is_null($this->published_at);
    }
}

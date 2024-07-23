<?php

namespace Winter\Search\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Winter\Search\Behaviors\Searchable;
use Winter\Search\Database\Factories\SearchableModelFactory;
use Winter\Storm\Database\Model;
use Winter\Storm\Database\Traits\ArraySource;

class SearchableModel extends Model
{
    use ArraySource;
    use HasFactory;

    public $implement = [
        Searchable::class,
    ];

    public $fillable = [
        'title',
        'description',
        'content',
        'keywords',
    ];

    public $searchable = [
        'title',
        'description',
        'keywords',
        'content',
    ];

    public $recordSchema = [
        'title' => 'string',
        'description' => 'string',
        'content' => 'text',
        'keywords' => 'string',
    ];

    protected static function newFactory()
    {
        return SearchableModelFactory::new();
    }
}

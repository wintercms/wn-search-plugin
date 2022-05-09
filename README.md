# Search Plugin

[![Build Status](https://img.shields.io/github/workflow/status/wintercms/wn-search-plugin/Tests)](https://github.com/wintercms/wn-search-plugin/actions)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/wintercms/wn-search-plugin/blob/master/LICENCE.md)
[![Discord](https://img.shields.io/discord/816852513684193281?label=discord&style=flat-square)](https://discord.gg/D5MFSPH6Ux)

Adds full-text searching capabilities to Winter, built on the foundations of [Laravel Scout](https://github.com/laravel/scout).
The plugin acts primarily as a wrapper for Laravel Scout, and provides its entire suite of functionality within Winter's
architecture, but also includes additional capabilities to make its use in Winter even easier.

## Requirements

- PHP 8.0 or above
- Winter CMS 1.2.0 or above (due to Laravel 9 requirement)

## Getting started

To install the plugin, you may install it through the [Winter CMS Marketplace](https://github.com/wintercms/wn-search-plugin), or you may install it using Composer:

```bash
composer require winter/wn-search-plugin
```

Then, run the migrations to ensure the plugin is enabled:

```bash
php artisan winter:up
```

## Configuration

Configuration for this plugin is chiefly done through the `search.php` configuration file. You can publish this configuration into your project's `config` directory by running the following command:

```bash
php artisan vendor:publish --provider="Winter\Search\Plugin"
```

This will create your own configuration file at `config/winter/search/search.php`, in which you will be able to override all default configuration values.

## Preparing your models

As this is a wrapper, you can use [all the base functionality](https://laravel.com/docs/9.x/scout) that Laravel Scout provides. There are only a couple of subtle differences with the Search plugin's implementation:

- Configuration values are stored within the `search` key. Wherever there is mention of a `scout` configuration value, you must use `search` instead.
- Soft deleted models are determined by the usage of the `Winter\Storm\Database\Traits\SoftDelete` trait, not the base Laravel `SoftDeletes` trait.

To make a particular database model searchable, you simply add the `Winter\Search\Behaviors\Searchable` behavior to that model. This behavior will register a model observer that will automatically synchronise the model records to an index:

```php
<?php

namespace Winter\Plugin\Models;

use Model;

class MyModel extends Model
{
    public $implement = [
        \Winter\Search\Behaviors\Searchable::class,
    ];
}
```

For Halcyon models, you must instead use the `Winter\Search\Behaviors\Halcyon\Searchable` behavior, in order to correctly hook into the unique functionality that Halcyon provides.

As the model is created, updated or deleted, the index will automatically be updated to reflect the state of that model record.

### Configuring searchable data

By default, the entire model is converted to an array form and persisted in the search index. If you would like to limit the data that is stored in the index, you can provide a `$searchable` property in the model. This property will represent all the model attributes that you would like to store in the index:

```php
<?php

namespace Winter\Plugin\Models;

use Model;

class Post extends Model
{
    public $implement = [
        \Winter\Search\Behaviors\Searchable::class,
    ];

    public $searchable = [
        'title',
        'summary'
    ];
}
```

If you want even more control over the data, you may override the `toSearchableArray` method:

```php
<?php

namespace Winter\Plugin\Models;

use Model;

class Post extends Model
{
    public $implement = [
        \Winter\Search\Behaviors\Searchable::class,
    ];

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();

        // Customize the data array...

        return $array;
    }
}
```

### Adding search to third-party models

You can add search functionality to third-party plugins through use of the [Dynamic Class Extension](https://wintercms.com/docs/services/behaviors) functionality in Winter. This can be done through your `Plugin.php` registration file, generally within the `boot()` method.

When extending a model in this fashion, you will also likely need to specify the searchable data you wish to include in your search index, using the `$searchable` property or `toSearchableArray()` method specified previously.

```php
<?php

namespace Winter\Plugin;

class Plugin extends \System\Classes\PluginBase
{
    public function boot()
    {
        \ThirdParty\Plugin\Models\Model::extend(function ($model) {
            $model->implement[] = \Winter\Search\Behaviors\Searchable::class;

            // Add a dynamic property to specify the searchable data
            $model->addDynamicProperty('searchable', [
                'id',
                'title',
                'description',
            ]);

            // Or, add a dynamic method instead.
            $model->addDynamicMethod('toSearchableArray', function () use ($model) {
                $array = $model->toArray();

                // Customize the data array...

                return $array;
            });
        });
    }
}
```

### Configuring the model ID

Normally, the primary key of the model will act as the model's unique ID that is stored in the search index. If you wish to use another column to act as the identifier for a model, you may override the `getSearchKey` and `getSearchKeyName` methods to customise this behaviour.

```php
<?php

namespace Winter\Plugin\Models;

use Model;

class User extends Model
{
    public $implement = [
        \Winter\Search\Behaviors\Searchable::class,
    ];

    /**
     * Get the value used to index the model.
     *
     * @return mixed
     */
    public function getSearchKey()
    {
        return $this->email;
    }

    /**
     * Get the key name used to index the model.
     *
     * @return mixed
     */
    public function getSearchKeyName()
    {
        return 'email';
    }
}
```

> **NOTE:** Some search providers, such as Meilisearch, enforce restrictions on the characters allowed for IDs. To be safe, we recommend that you restrict IDs to using the following characters: `A-Z`, `a-z`, `0-9` and dashes and underscores. Any other characters may prevent some search providers from indexing your record.

## Registering search handlers

Once your models are prepared for searching capabilities, you may register a search handler that allows the models to be searched by the included components.

Registration of a search handler takes place in your `Plugin.php` file by specifying a `registerSearchHandlers` method that returns an array.

```php
<?php

namespace Acme\Plugin;

class Plugin extends \System\Classes\PluginBase
{
    public function registerSearchHandlers()
    {
        return [
            'mySearch' => [
                'name' => 'My Search',
                'model' => \Winter\Plugin\Models\Post::class,
                'record' => [
                    'title' => 'title',
                    'image' => 'featured_image',
                    'description' => 'description',
                    'url' => 'url',
                ]
            ]
        ];
    }
}
```

Each array item should specify a key name that represents the ID of the search handler. The following properties can be specified as part of the handler:

Property | Description
-------- | -----------
`name` | The human-readable name of your search handler. This will be used for grouped results.
`model` | The name of the class you wish to search with this handler.
`record` | A handler for each record returned in the results. See below for more information on the valid configurations for this property.

### Record handler

Each search handler may also provide a result handler to finely tune how you wish to display or filter the results. At its most simplest, the record handler simply expects an array to be returned for each record that contains 4 properties:

- `title`: The title of the result.
- `image`: The path to a corresponding image for the result.
- `description`: Additional context for the result.
- `url`: The URL that the result will point to.

You may, of course, define additional properties in your array.

The record handler can be configured in a number of different ways.

#### Array map of fields

You can simply return an array with the properties above that map to the corresponding fields within the model.

```php
'record' => [
    'title' => 'title',
    'image' => 'image',
    'description' => 'description',
    'url' => 'url',
]
```

#### Array map with callbacks

Similar to the above, you may also specify some or all properties to use a callback method that will be fed two arguments: the model instance of each result, and the original query.

```php
'record' => [
    'title' => 'title',
    'image' => 'image',
    'description' => function ($model, $query) {
        return substr($model->description, 0, 100) . '...';
    },
    'url' => 'url',
]
```

#### Callback method

You may also make the entire handler go through a callback method. This gives the greatest level of control, as you may also filter records out.

The callback method should always return an array with the main properties defined above, but you may include any additional properties as you wish.

The callback method may also return `false` to exclude the record from the results.

```php
'record' => function ($model, $query) {
    if ($model->isNotPublished()) {
        return false;
    }

    return [
        'title' => $model->title,
        'image' => $model->image->url(),
        'description' => $model->getDescription(),
        'url' => $model->getUrl(),
    ];
}
```

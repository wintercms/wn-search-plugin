# Search Plugin

[![Build Status](https://img.shields.io/github/workflow/status/wintercms/wn-search-plugin/Tests)](https://github.com/wintercms/wn-search-plugin/actions)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/wintercms/wn-search-plugin/blob/master/LICENCE.md)
[![Discord](https://img.shields.io/discord/816852513684193281?label=discord&style=flat-square)](https://discord.gg/D5MFSPH6Ux)

Adds full-text searching capabilities to Winter, built on the foundations of [Laravel Scout](https://github.com/laravel/scout).
The plugin acts primarily as a wrapper for Laravel Scout, and provides its entire suite of functionality within Winter's
architecture, but also includes additional capabilities to make its use in Winter even easier.

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

## Usage

As this is a wrapper, you can use [all the base functionality](https://laravel.com/docs/9.x/scout) that Laravel Scout provides. There are only a couple of subtle differences with the Search plugin's implementation:

- Configuration values are stored within the `search` key. Wherever there is mention of a `scout` configuration value, you must use `search` instead.
- Soft deleted models are determined by the usage of the `Winter\Storm\Database\Traits\SoftDelete` trait, not the base Laravel `SoftDeletes` trait.

To make a particular model searchable, you simply add the `Winter\Search\Traits\Searchable` trait to that model. This trait will register a model observer that will automatically synchronise the model records to an index:

```php
<?php

namespace Winter\Plugin\Models;

use Model;
use Winter\Search\Traits\Searchable;

class MyModel extends Model
{
    use Searchable;
}
```

As the model is created, updated or deleted, the index will automatically be updated to reflect the state of that model record.

### Configuring searchable data

By default, the entire model is converted to an array form and persisted in the search index. If you would like to limit the data that is stored in the index, you can provide a `$searchable` property in the model. This property will represent all the model attributes that you would like to store in the index:

```php
<?php

namespace Winter\Plugin\Models;

use Model;
use Winter\Search\Traits\Searchable;

class Post extends Model
{
    use Searchable;

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
use Winter\Search\Traits\Searchable;

class Post extends Model
{
    use Searchable;

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

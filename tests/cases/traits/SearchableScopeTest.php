<?php

namespace Winter\Search\Tests\Cases\Traits;

use Mockery as m;
use PluginTestCase;
use Winter\Storm\Database\Builder;
use Winter\Search\Classes\SearchableScope;

class SearchableScopeTest extends PluginTestCase
{
    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testChunksById()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('macro')->with('searchable', m::on(function ($callback) use ($builder) {
            $builder->shouldReceive('chunkById')->with(500, m::type(\Closure::class));
            $callback($builder, 500);

            return true;
        }));
        $builder->shouldReceive('macro')->with('unsearchable', m::on(function ($callback) use ($builder) {
            $builder->shouldReceive('chunkById')->with(500, m::type(\Closure::class));
            $callback($builder, 500);

            return true;
        }));

        (new SearchableScope())->extend($builder);
    }
}

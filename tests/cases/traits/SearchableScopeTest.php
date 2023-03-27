<?php

namespace Winter\Search\Tests\Cases\Traits;

use Mockery as m;
use System\Tests\Bootstrap\PluginTestCase;
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

    /**
     * Flush model event listeners.
     *
     * The models in Winter use a static property to store their events. These will need to be
     * targeted and reset, ready for a new test cycle.
     *
     * Pivot models are an exception since they are internally managed.
     */
    protected function flushModelEventListeners(): void
    {

    }
}

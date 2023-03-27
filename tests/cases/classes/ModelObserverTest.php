<?php

namespace Winter\Search\Tests\Cases\Classes;

use Config;
use Mockery as m;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Search\Classes\ModelObserver;
use Winter\Search\Tests\Fixtures\SearchableModelWithSoftDelete;
use Winter\Search\Tests\Fixtures\SearchableModelWithSensitiveAttributes;
use Winter\Storm\Database\Model;

class ModelObserverTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Config::clearResolvedInstances();
        Config::shouldReceive('get')->with('search.after_commit', m::any())->andReturn(false);
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testSavedHandlerMakesModelSearchable()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock();
        $model->shouldReceive('searchIndexShouldBeUpdated')->andReturn(true);
        $model->shouldReceive('shouldBeSearchable')->andReturn(true);
        $model->shouldReceive('searchable')->once();
        $observer->saved($model);
    }

    public function testSavedHandlerDoesntMakeModelSearchableWhenSearchShouldntUpdate()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock();
        $model->shouldReceive('searchIndexShouldBeUpdated')->andReturn(false);
        $model->shouldReceive('shouldBeSearchable')->andReturn(true);
        $model->shouldReceive('searchable')->never();
        $observer->saved($model);
    }

    public function testSavedHandlerDoesntMakeModelSearchableWhenDisabled()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock();
        $observer->disableSyncingFor(get_class($model));
        $model->shouldReceive('searchable')->never();
        $observer->saved($model);
        $observer->enableSyncingFor(get_class($model));
    }

    public function testSavedHandlerMakesModelUnsearchableWhenDisabledPerModelRule()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock();
        $model->shouldReceive('searchIndexShouldBeUpdated')->andReturn(true);
        $model->shouldReceive('shouldBeSearchable')->andReturn(false);
        $model->shouldReceive('wasSearchableBeforeUpdate')->andReturn(true);
        $model->shouldReceive('searchable')->never();
        $model->shouldReceive('unsearchable')->once();
        $observer->saved($model);
    }

    public function testSavedHandlerDoesntMakeModelUnsearchableWhenDisabledPerModelRuleAndAlreadyUnsearchable()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock(Model::class);
        $model->shouldReceive('searchIndexShouldBeUpdated')->andReturn(true);
        $model->shouldReceive('shouldBeSearchable')->andReturn(false);
        $model->shouldReceive('wasSearchableBeforeUpdate')->andReturn(false);
        $model->shouldReceive('searchable')->never();
        $model->shouldReceive('unsearchable')->never();
        $observer->saved($model);
    }

    public function testDeletedHandlerDoesntMakeModelUnsearchableWhenAlreadyUnsearchable()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock();
        $model->shouldReceive('wasSearchableBeforeDelete')->andReturn(false);
        $model->shouldReceive('unsearchable')->never();
        $observer->deleted($model);
    }

    public function testDeletedHandlerMakesModelUnsearchable()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock();
        $model->shouldReceive('wasSearchableBeforeDelete')->andReturn(true);
        $model->shouldReceive('unsearchable')->once();
        $observer->deleted($model);
    }

    public function testDeletedHandlerOnSoftDeleteModelMakesModelUnsearchable()
    {
        $this->setSoftDeleting(false);

        $observer = new ModelObserver;
        $model = m::mock(SearchableModelWithSoftDelete::class);
        $model->shouldReceive('wasSearchableBeforeDelete')->andReturn(true);
        $model->shouldReceive('searchable')->never();
        $model->shouldReceive('unsearchable')->once();
        $observer->deleted($model);
    }

    public function test_update_on_sensitive_attributes_triggers_search()
    {
        $this->setSoftDeleting(false);

        $model = m::mock(
            new SearchableModelWithSensitiveAttributes([
                'first_name' => 'taylor',
                'last_name' => 'Otwell',
                'remember_token' => 123,
                'password' => 'secret',
            ])
        )->makePartial();

        // Let's pretend it's in sync with the database.
        $model->syncOriginal();

        // Update
        $model->password = 'extremelySecurePassword';
        $model->first_name = 'Taylor';

        // Assertions
        $model->shouldReceive('searchable')->once();
        $model->shouldReceive('unsearchable')->never();

        $observer = new ModelObserver;
        $observer->saved($model);
    }

    public function testUpdateOnNonSensitiveAttributesDoesntTriggerSearch()
    {
        $this->setSoftDeleting(false);

        $model = m::mock(
            new SearchableModelWithSensitiveAttributes([
                'first_name' => 'Ben',
                'last_name' => 'Thomson',
                'remember_token' => 123,
                'password' => 'secret',
            ])
        )->makePartial();

        // Let's pretend it's in sync with the database.
        $model->syncOriginal();

        // Update
        $model->password = 'extremelySecurePassword';
        $model->remember_token = 456;

        // Assertions
        $model->shouldReceive('searchable')->never();
        $model->shouldReceive('unsearchable')->never();

        $observer = new ModelObserver;
        $observer->saved($model);
    }

    public function testUnsearchableShouldBeCalledWhenDeleting()
    {
        $this->setSoftDeleting(false);

        $model = m::mock(
            new SearchableModelWithSensitiveAttributes([
                'first_name' => 'Ben',
                'last_name' => 'Thomson',
                'remember_token' => 123,
                'password' => 'secret',
            ])
        )->makePartial();

        // Let's pretend it's in sync with the database.
        $model->syncOriginal();

        // Assertions
        $model->shouldReceive('searchable')->never();
        $model->shouldReceive('unsearchable')->once();

        $observer = new ModelObserver;
        $observer->deleted($model);
    }

    public function testDeletedHandlerMakesSoftDeletedModelUnsearchableWhenItShouldNotBeSearchable()
    {
        $this->setSoftDeleting(true);

        $observer = new ModelObserver;
        $model = m::mock(SearchableModelWithSoftDelete::class);
        $model->shouldReceive('searchShouldUpdate')->never(); // The saved event is forced
        $model->shouldReceive('shouldBeSearchable')->andReturn(false); // Should not be searchable
        $model->shouldReceive('wasSearchableBeforeDelete')->andReturn(true);
        $model->shouldReceive('wasSearchableBeforeUpdate')->andReturn(true);
        $model->shouldReceive('searchable')->never();
        $model->shouldReceive('unsearchable')->once();
        $observer->deleted($model);
    }

    public function testDeletedHandlerMakesSoftDeletedModelSearchableWhenItShouldBeSearchable()
    {
        $this->setSoftDeleting(true);

        $observer = new ModelObserver;
        $model = m::mock(SearchableModelWithSoftDelete::class);
        $model->shouldReceive('searchShouldUpdate')->never(); // The saved event is forced
        $model->shouldReceive('shouldBeSearchable')->andReturn(true); // Should be searchable
        $model->shouldReceive('wasSearchableBeforeDelete')->andReturn(true);
        $model->shouldReceive('searchable')->once();
        $model->shouldReceive('unsearchable')->never();
        $observer->deleted($model);
    }

    public function testRestoredHandlerMakesModelSearchable()
    {
        $this->setSoftDeleting(true);

        $observer = new ModelObserver;
        $model = m::mock(SearchableModelWithSoftDelete::class);
        $model->shouldReceive('searchShouldUpdate')->never();
        $model->shouldReceive('shouldBeSearchable')->andReturn(true);
        $model->shouldReceive('searchable')->once();
        $model->shouldReceive('unsearchable')->never();
        $observer->restored($model);
    }

    public function testUnsearchableShouldBeCalledWhenDeletingSoftDeleteEnabled()
    {
        $this->setSoftDeleting(true);

        $model = m::mock(
            new SearchableModelWithSensitiveAttributes([
                'first_name' => 'Ben',
                'last_name' => 'Thomson',
                'remember_token' => 123,
                'password' => 'secret',
            ])
        )->makePartial();

        // Let's pretend it's in sync with the database.
        $model->syncOriginal();

        // Assertions
        $model->shouldReceive('searchable')->once();
        $model->shouldReceive('unsearchable')->never();

        $observer = new ModelObserver;
        $observer->deleted($model);
    }

    protected function setSoftDeleting(bool $enabled)
    {
        Config::shouldReceive('get')->with('search.soft_delete', m::any())->andReturn($enabled);
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

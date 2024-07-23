<?php

namespace Winter\Search\Tests\Cases\Models;

use System\Tests\Bootstrap\PluginTestCase;
use Winter\Search\Tests\Fixtures\SearchableModel;

class SearchableModelTest extends PluginTestCase
{
    public function testSearchWithRelevance()
    {
        SearchableModel::truncate();
        $records = SearchableModel::factory()->count(50)->create();

        // Second most relevant
        $records[0]->update(['title' => 'TestQuery TestQuery TestQuery']);
        // Third most relevant
        $records[1]->update(['title' => 'TestQuery TestQuery']);
        // Fourth most relevant
        $records[2]->update(['description' => 'TestQuery TestQuery TestQuery']);
        // Fifth most relevant
        $records[3]->update(['description' => 'TestQuery TestQuery']);
        // Most relevant
        $records[4]->update(['title' => 'TestQuery TestQuery TestQuery', 'description' => 'TestQuery TestQuery TestQuery']);

        $results = SearchableModel::search('TestQuery')->getWithRelevance();

        $recordIds = $records->slice(0, 5)->pluck('id')->toArray();
        $resultIds = $results->slice(0, 5)->pluck('id')->toArray();

        $this->assertEquals($recordIds[4], $resultIds[0]);
        $this->assertEquals($recordIds[0], $resultIds[1]);
        $this->assertEquals($recordIds[1], $resultIds[2]);
        $this->assertEquals($recordIds[2], $resultIds[3]);
        $this->assertEquals($recordIds[3], $resultIds[4]);

        $result = SearchableModel::search('TestQuery')->firstRelevant();

        $this->assertEquals($recordIds[4], $result->id);
    }
}

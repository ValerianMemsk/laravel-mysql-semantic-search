<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use PHPUnit\Framework\TestCase;
use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;
use ValerianMemsk\SemanticSearch\Embedders\EmbedderManager;
use ValerianMemsk\SemanticSearch\Services\SemanticSearchService;

class RrfTest extends TestCase
{
    public function test_combine_rrf_ranks_items_present_in_both_lists_higher(): void
    {
        $embedderManager = $this->createMock(EmbedderManager::class);
        $vectorDriver = $this->createMock(VectorDriverContract::class);
        $service = new SemanticSearchService($embedderManager, $vectorDriver);

        $list1 = [10, 20, 30];
        $list2 = [30, 40, 10];

        $combined = $service->combineRRF($list1, $list2, 60);

        $this->assertEqualsCanonicalizing([10, 20, 30, 40], $combined);
        $this->assertTrue(in_array($combined[0], [10, 30]));
        $this->assertTrue(in_array($combined[1], [10, 30]));
    }

    public function test_combine_rrf_preserves_first_rank_for_top_item(): void
    {
        $embedderManager = $this->createMock(EmbedderManager::class);
        $vectorDriver = $this->createMock(VectorDriverContract::class);
        $service = new SemanticSearchService($embedderManager, $vectorDriver);

        $list1 = [100, 200];
        $list2 = [100, 300];

        $combined = $service->combineRRF($list1, $list2, 60);

        $this->assertEquals(100, $combined[0]);
    }
}

<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use ValerianMemsk\SemanticSearch\Drivers\MariaDbVectorDriver;
use ValerianMemsk\SemanticSearch\Drivers\MySqlVectorDriver;
use ValerianMemsk\SemanticSearch\Models\EntityEmbedding;

class VectorDriverTest extends TestCase
{
    public function test_mariadb_apply_distance_query_builds_expected_sql(): void
    {
        $driver = new MariaDbVectorDriver;
        $query = $driver->applyDistanceQuery(EntityEmbedding::query(), [0.1, 0.2], 0.5, 10);

        $sql = $query->toSql();

        $this->assertStringContainsString('VEC_DISTANCE_COSINE', $sql);
        $this->assertStringContainsString('VEC_FromText', $sql);
        $this->assertSame('[0.1,0.2]', $query->getBindings()[0]);
    }

    public function test_mysql_apply_distance_query_builds_expected_sql(): void
    {
        $driver = new MySqlVectorDriver;
        $query = $driver->applyDistanceQuery(EntityEmbedding::query(), [0.1, 0.2], 0.5, 10);

        $sql = $query->toSql();

        $this->assertStringContainsString('VECTOR_DISTANCE', $sql);
        $this->assertStringContainsString('STRING_TO_VECTOR', $sql);
    }

    public function test_mariadb_parse_from_storage_unpacks_binary_floats(): void
    {
        $driver = new MariaDbVectorDriver;

        $this->assertEqualsWithDelta([0.5, 0.25], $driver->parseFromStorage(pack('f*', 0.5, 0.25)), 0.00001);
    }

    public function test_mysql_parse_from_storage_unpacks_binary_floats(): void
    {
        $driver = new MySqlVectorDriver;

        $this->assertEqualsWithDelta([0.5, 0.25], $driver->parseFromStorage(pack('f*', 0.5, 0.25)), 0.00001);
    }
}

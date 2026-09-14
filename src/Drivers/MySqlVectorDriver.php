<?php

namespace ValerianMemsk\SemanticSearch\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;

class MySqlVectorDriver implements VectorDriverContract
{
    /**
     * Apply MySQL 9.0+ vector distance query (VECTOR_DISTANCE).
     */
    public function applyDistanceQuery(Builder $query, array $vector, float $threshold, int $limit): Builder
    {
        $json = json_encode($vector);

        return $query
            ->selectRaw('entity_id, VECTOR_DISTANCE(embedding, STRING_TO_VECTOR(?), "COSINE") as distance', [$json])
            ->having('distance', '<', $threshold)
            ->orderBy('distance', 'asc')
            ->limit($limit);
    }

    /**
     * Prepare vector array for MySQL 9.0+ storage.
     */
    public function prepareForStorage(array $vector): mixed
    {
        $json = json_encode($vector);

        return DB::raw("STRING_TO_VECTOR('{$json}')");
    }

    /**
     * Parse raw database value into a standard float array.
     */
    public function parseFromStorage(mixed $value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && str_starts_with($value, '[')) {
            return json_decode($value, true);
        }

        if (is_string($value)) {
            return array_values(unpack('f*', $value));
        }

        return (array) $value;
    }
}

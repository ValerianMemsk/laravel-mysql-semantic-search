<?php

namespace ValerianMemsk\SemanticSearch\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;

class MariaDbVectorDriver implements VectorDriverContract
{
    /**
     * Apply MariaDB vector distance query (VEC_DISTANCE_COSINE).
     */
    public function applyDistanceQuery(Builder $query, array $vector, float $threshold, int $limit): Builder
    {
        $json = json_encode($vector);

        return $query
            ->selectRaw('entity_id, VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) as distance', [$json])
            ->having('distance', '<', $threshold)
            ->orderBy('distance', 'asc')
            ->limit($limit);
    }

    /**
     * Prepare vector array for MariaDB storage.
     */
    public function prepareForStorage(array $vector): mixed
    {
        $json = json_encode($vector);

        return DB::raw("VEC_FromText('{$json}')");
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

        // MariaDB returns a binary string of 32-bit floats.
        // unpack returns a 1-based array; use array_values to make it 0-based.
        return array_values(unpack('f*', $value));
    }
}

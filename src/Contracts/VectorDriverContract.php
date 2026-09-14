<?php

namespace ValerianMemsk\SemanticSearch\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface VectorDriverContract
{
    /**
     * Apply vector distance calculation and filter to an EntityEmbedding query builder.
     *
     * @param  Builder  $query
     * @param  array<float>  $vector
     * @param  float  $threshold
     * @param  int  $limit
     * @return Builder
     */
    public function applyDistanceQuery(Builder $query, array $vector, float $threshold, int $limit): Builder;

    /**
     * Prepare vector array for database storage.
     *
     * @param  array<float>  $vector
     * @return mixed
     */
    public function prepareForStorage(array $vector): mixed;

    /**
     * Parse raw database value into a standard float array.
     *
     * @param  mixed  $value
     * @return array<float>|null
     */
    public function parseFromStorage(mixed $value): ?array;
}

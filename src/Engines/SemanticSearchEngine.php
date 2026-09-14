<?php

namespace ValerianMemsk\SemanticSearch\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;

class SemanticSearchEngine extends Engine
{
    /**
     * The fallback search engine.
     */
    protected Engine $fallbackEngine;

    /**
     * Create a new semantic search engine instance.
     */
    public function __construct(Engine $fallbackEngine)
    {
        $this->fallbackEngine = $fallbackEngine;
    }

    /**
     * Update the given models in the index.
     *
     * @param  Collection  $models
     */
    public function update($models): void
    {
        $this->fallbackEngine->update($models);
    }

    /**
     * Remove the given models from the index.
     *
     * @param  Collection  $models
     */
    public function delete($models): void
    {
        $this->fallbackEngine->delete($models);
    }

    /**
     * Perform the given search on the engine.
     */
    public function search(Builder $builder): mixed
    {
        $semanticResult = $this->performSemanticSearch($builder, ['limit' => 100]);
        if ($semanticResult === null) {
            return $this->fallbackEngine->search($builder);
        }

        $fallbackResult = $this->fallbackEngine->search($builder);
        $fallbackIds = $this->fallbackEngine->mapIds($fallbackResult)->toArray();

        // Combine using Reciprocal Rank Fusion (RRF)
        $combinedIds = SemanticSearch::combineRRF($semanticResult['ids'], $fallbackIds);

        $limit = $builder->limit ?? 100;
        $slicedIds = array_slice($combinedIds, 0, $limit);

        return [
            'is_semantic' => true,
            'ids' => $slicedIds,
            'total' => count($combinedIds),
        ];
    }

    /**
     * Perform the given search with pagination.
     *
     * @param  int  $perPage
     * @param  int  $page
     */
    public function paginate(Builder $builder, $perPage, $page): mixed
    {
        $semanticResult = $this->performSemanticSearch($builder, ['limit' => 100]);

        if ($semanticResult === null) {
            return $this->fallbackEngine->paginate($builder, $perPage, $page);
        }

        // Retrieve results from fallback engine by simulating search with limit
        $clonedBuilder = clone $builder;
        $clonedBuilder->limit = 100;
        $fallbackResult = $this->fallbackEngine->search($clonedBuilder);
        $fallbackIds = $this->fallbackEngine->mapIds($fallbackResult)->toArray();

        // Combine using Reciprocal Rank Fusion (RRF)
        $combinedIds = SemanticSearch::combineRRF($semanticResult['ids'], $fallbackIds);

        $offset = ($page - 1) * $perPage;
        $slicedIds = array_slice($combinedIds, $offset, $perPage);

        return [
            'is_semantic' => true,
            'ids' => $slicedIds,
            'total' => count($combinedIds),
        ];
    }

    /**
     * Map the given results to an array of primary keys.
     *
     * @param  mixed  $results
     */
    public function mapIds($results): \Illuminate\Support\Collection
    {
        if (isset($results['is_semantic'])) {
            return collect($results['ids']);
        }

        return $this->fallbackEngine->mapIds($results);
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  Model  $model
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if (isset($results['is_semantic'])) {
            $ids = $results['ids'];
            if (empty($ids)) {
                return $model->newCollection();
            }

            $query = $model->whereIn($model->getQualifiedKeyName(), $ids);

            if ($builder->queryCallback) {
                call_user_func($builder->queryCallback, $query);
            }

            $models = $query->get();

            // Re-order results by vector/RRF distance ranking
            return $models->sortBy(function ($m) use ($ids) {
                return array_search($m->getKey(), $ids);
            })->values();
        }

        return $this->fallbackEngine->map($builder, $results, $model);
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  mixed  $results
     * @param  Model  $model
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if (isset($results['is_semantic'])) {
            $ids = $results['ids'];
            if (empty($ids)) {
                return LazyCollection::empty();
            }

            $query = $model->whereIn($model->getQualifiedKeyName(), $ids);

            if ($builder->queryCallback) {
                call_user_func($builder->queryCallback, $query);
            }

            return LazyCollection::make(function () use ($query, $ids) {
                $models = $query->get()->sortBy(function ($m) use ($ids) {
                    return array_search($m->getKey(), $ids);
                })->values();

                foreach ($models as $m) {
                    yield $m;
                }
            });
        }

        return $this->fallbackEngine->lazyMap($builder, $results, $model);
    }

    /**
     * Get the total count from the given results.
     *
     * @param  mixed  $results
     */
    public function getTotalCount($results): int
    {
        if (isset($results['is_semantic'])) {
            return $results['total'];
        }

        return $this->fallbackEngine->getTotalCount($results);
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  Model  $model
     */
    public function flush($model): void
    {
        $this->fallbackEngine->flush($model);
    }

    /**
     * Create a new index.
     *
     * @param  string  $name
     */
    public function createIndex($name, array $options = []): mixed
    {
        return $this->fallbackEngine->createIndex($name, $options);
    }

    /**
     * Delete a given index.
     *
     * @param  string  $name
     */
    public function deleteIndex($name): mixed
    {
        return $this->fallbackEngine->deleteIndex($name);
    }

    /**
     * Perform the actual semantic vector search.
     */
    protected function performSemanticSearch(Builder $builder, array $options = []): ?array
    {
        $model = $builder->model;
        $modelClass = get_class($model);
        $limit = $options['limit'] ?? $builder->limit ?? 50;

        $fetchLimit = isset($options['offset']) ? $limit : $limit * 2;

        $ids = SemanticSearch::searchIds($modelClass, $builder->query, $fetchLimit);

        if ($ids === null) {
            return null; // Signals fallback
        }

        if (isset($options['offset'])) {
            $ids = array_slice($ids, $options['offset'], $limit);
        } else {
            $ids = array_slice($ids, 0, $limit);
        }

        return [
            'is_semantic' => true,
            'ids' => $ids,
            'total' => count($ids),
        ];
    }
}

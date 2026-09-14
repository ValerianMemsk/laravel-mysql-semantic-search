<?php

namespace ValerianMemsk\SemanticSearch\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array|null searchIds(string $modelClass, string $queryText, int $limit = 50)
 * @method static array combineRRF(array $list1, array $list2, ?int $k = null)
 * @method static string preprocessQuery(string $queryText)
 * @method static array embedText(string $text, ?string $driver = null)
 * @method static void embedModel(\Illuminate\Database\Eloquent\Model $model, ?string $driver = null)
 * @method static bool isEmbeddable(string $className)
 * @method static array discoverEmbeddableModels(?array $paths = null)
 * @method static \ValerianMemsk\SemanticSearch\Contracts\EmbedderContract embedder(?string $driver = null)
 * @method static \ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract vectorDriver()
 *
 * @see \ValerianMemsk\SemanticSearch\Services\SemanticSearchService
 */
class SemanticSearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'semantic-search';
    }
}

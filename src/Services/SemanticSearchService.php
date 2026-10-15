<?php

namespace ValerianMemsk\SemanticSearch\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;
use ValerianMemsk\SemanticSearch\Contracts\EmbedderContract;
use ValerianMemsk\SemanticSearch\Contracts\QueryPreprocessorContract;
use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;
use ValerianMemsk\SemanticSearch\Embedders\EmbedderManager;
use ValerianMemsk\SemanticSearch\Models\EntityEmbedding;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class SemanticSearchService
{
    protected EmbedderManager $embedderManager;
    protected VectorDriverContract $vectorDriver;

    public function __construct(EmbedderManager $embedderManager, VectorDriverContract $vectorDriver)
    {
        $this->embedderManager = $embedderManager;
        $this->vectorDriver = $vectorDriver;
    }

    /**
     * Get the active embedder instance.
     */
    public function embedder(?string $driver = null): EmbedderContract
    {
        return $this->embedderManager->driver($driver);
    }

    /**
     * Get the vector driver instance.
     */
    public function vectorDriver(): VectorDriverContract
    {
        return $this->vectorDriver;
    }

    /**
     * Search for entities by semantic similarity.
     *
     * @param  string  $modelClass
     * @param  string  $queryText
     * @param  int  $limit
     * @return array<int|string>|null Returns array of matching model IDs or null on failure/unsupported
     */
    public function searchIds(string $modelClass, string $queryText, int $limit = 50): ?array
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        if (! $this->isEmbeddable($modelClass)) {
            return null;
        }

        if (empty(trim($queryText))) {
            return null;
        }

        try {
            $embeddingModel = config('semantic-search.models.embedding', EntityEmbedding::class);

            $exists = $embeddingModel::query()->where('entity_type', $modelClass)->exists();
            if (! $exists) {
                return null;
            }

            $vector = $this->embedText($queryText);
            $threshold = $this->getThreshold();

            $query = $embeddingModel::query()->where('entity_type', $modelClass);
            $query = $this->vectorDriver->applyDistanceQuery($query, $vector, $threshold, $limit);

            return $query->pluck('entity_id')->toArray();

        } catch (Exception $e) {
            Log::warning("[Semantic Search] Failed to search for {$modelClass}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Get the configured cosine distance threshold.
     */
    public function getThreshold(): float
    {
        return (float) config('semantic-search.threshold', 0.49);
    }

    /**
     * Combine multiple arrays of ranked IDs using Reciprocal Rank Fusion (RRF).
     *
     * @param  array  $list1  First ranked list of IDs (e.g. from semantic search)
     * @param  array  $list2  Second ranked list of IDs (e.g. from keyword search)
     * @param  int|null  $k  Constant to prevent high-rank bias (default from config or 60)
     * @return array Ranked unique IDs
     */
    public function combineRRF(array $list1, array $list2, ?int $k = null): array
    {
        $k = $k ?? (int) config('semantic-search.rrf.k', 60);
        $scores = [];

        foreach ([$list1, $list2] as $list) {
            foreach (array_values($list) as $rank => $id) {
                $idKey = (string) $id;
                if (! isset($scores[$idKey])) {
                    $scores[$idKey] = 0.0;
                }

                // rank is 0-indexed, so 1-indexed rank is rank + 1
                $scores[$idKey] += 1.0 / ($k + $rank + 1);
            }
        }

        arsort($scores);

        // Map back to original typed scalar values (int if numeric)
        return array_map(function ($id) {
            return is_numeric($id) ? (int) $id : $id;
        }, array_keys($scores));
    }

    /**
     * Preprocess search query / document text using configured preprocessors.
     */
    public function preprocessQuery(string $queryText): string
    {
        if (empty(trim($queryText))) {
            return $queryText;
        }

        $preprocessors = config('semantic-search.preprocessors', [
            \ValerianMemsk\SemanticSearch\Preprocessors\DictionaryPreprocessor::class,
        ]);

        foreach ($preprocessors as $preprocessorClass) {
            try {
                if (class_exists($preprocessorClass)) {
                    /** @var QueryPreprocessorContract $preprocessor */
                    $preprocessor = app($preprocessorClass);
                    $queryText = $preprocessor->preprocess($queryText);
                }
            } catch (Exception $e) {
                Log::warning("[Semantic Search] Preprocessor {$preprocessorClass} error: ".$e->getMessage());
            }
        }

        return $queryText;
    }

    /**
     * Transform raw string into vector embeddings.
     *
     * @throws Exception
     */
    public function embedText(string $text, ?string $driver = null): array
    {
        $text = $this->preprocessQuery($text);

        return $this->embedder($driver)->embed($text);
    }

    /**
     * Generate and save embeddings for a given Eloquent model.
     *
     * @throws Exception
     */
    public function embedModel(Model $model, ?string $driver = null): void
    {
        if (! method_exists($model, 'embedding')) {
            throw new \InvalidArgumentException(
                'Model '.get_class($model).' does not support embeddings. Ensure it uses HasEmbeddings trait.'
            );
        }

        $text = method_exists($model, 'toSearchableText') ? $model->toSearchableText() : null;

        if (is_null($text) || trim($text) === '') {
            throw new \RuntimeException(
                'Could not determine searchable text for Model '.get_class($model).'. Implement toSemanticSearchArray() or toSearchableArray().'
            );
        }

        $vector = $this->embedText($text, $driver);

        $model->embedding()->updateOrCreate(
            [],
            ['embedding' => $vector]
        );
    }

    /**
     * Verify if a class uses the HasEmbeddings trait.
     */
    public function isEmbeddable(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $traits = class_uses_recursive($className);

        return in_array(HasEmbeddings::class, $traits);
    }

    /**
     * Discover all embeddable classes in given directories or default Model paths.
     */
    public function discoverEmbeddableModels(?array $paths = null): array
    {
        $models = [];
        $paths = $paths ?? config('semantic-search.discovery_paths', [
            function_exists('app_path') ? app_path('Models') : null,
        ]);

        foreach ($paths as $path) {
            if (! $path || ! is_dir($path)) {
                continue;
            }

            $finder = new Finder;
            $files = $finder->in($path)->files()->name('*.php');

            foreach ($files as $file) {
                $className = $this->extractFullyQualifiedClassName($file->getRealPath());
                if ($className && $this->isEmbeddable($className)) {
                    $models[] = $className;
                }
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * Extract the fully-qualified class name from a PHP source file.
     */
    public function extractFullyQualifiedClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $class = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_NAME_QUALIFIED || $tokens[$j][0] === T_STRING) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Skip `::class` (T_CLASS preceded by T_DOUBLE_COLON).
                $previous = $this->previousNonWhitespaceToken($tokens, $i);
                if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                    if ($tokens[$j][0] === T_STRING) {
                        $class = $tokens[$j][1];
                        break;
                    }
                }
                break;
            }
        }

        if (empty($class)) {
            return null;
        }

        return $namespace ? "{$namespace}\\{$class}" : $class;
    }

    /**
     * Get the nearest preceding token that is not whitespace.
     *
     * @param  array<int, array|string>  $tokens
     * @return array|string|null
     */
    private function previousNonWhitespaceToken(array $tokens, int $index): array|string|null
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }
}

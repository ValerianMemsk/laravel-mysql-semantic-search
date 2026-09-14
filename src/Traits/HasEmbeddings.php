<?php

namespace ValerianMemsk\SemanticSearch\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use ValerianMemsk\SemanticSearch\Jobs\GenerateModelEmbedding;
use ValerianMemsk\SemanticSearch\Models\EntityEmbedding;

/**
 * Trait HasEmbeddings
 *
 * Enables automatic generation of semantic embeddings on model save.
 */
trait HasEmbeddings
{
    /**
     * Boot the trait to automatically generate embeddings on save.
     */
    public static function bootHasEmbeddings(): void
    {
        static::saved(function ($model) {
            if (config('semantic-search.auto_embed_on_save', true)) {
                $queue = config('semantic-search.queue');
                $job = new GenerateModelEmbedding($model);

                if ($queue) {
                    $job->onQueue($queue);
                }

                dispatch($job);
            }
        });
    }

    /**
     * Get the model's polymorphic embedding relation.
     */
    public function embedding(): MorphOne
    {
        return $this->morphOne(
            config('semantic-search.models.embedding', EntityEmbedding::class),
            'entity'
        );
    }

    /**
     * Get the text representation of the model for embedding generation.
     * Can be customized via toSemanticSearchArray() or toSearchableArray().
     */
    public function toSearchableText(): ?string
    {
        $modelBase = Str::snake(class_basename($this));
        $parts = [];

        // 1. Try to build from toSemanticSearchArray()
        if (method_exists($this, 'toSemanticSearchArray')) {
            try {
                $data = $this->toSemanticSearchArray();

                foreach ($data as $key => $value) {
                    $val = trim(strip_tags((string) ($value ?? '')));
                    if ($val === '') {
                        continue;
                    }

                    $label = $this->translateEmbeddingLabel((string) $key, $modelBase);
                    $parts[] = "{$label}: {$val}";
                }

                if (! empty($parts)) {
                    return $this->formatEmbeddingText($parts);
                }
            } catch (\Throwable $e) {
                // Fallback on error
            }
        }

        // 2. Fallback to toSearchableArray()
        if (method_exists($this, 'toSearchableArray')) {
            $data = $this->toSearchableArray();

            // Exclude the primary key
            unset($data[$this->getKeyName()]);

            foreach ($data as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if (is_string($value) || is_numeric($value)) {
                    $label = $this->translateEmbeddingLabel((string) $key, $modelBase);
                    $val = trim(strip_tags((string) $value));
                    $parts[] = "{$label}: {$val}";
                }
            }

            if (! empty($parts)) {
                return $this->formatEmbeddingText($parts);
            }
        }

        return null;
    }

    /**
     * Translate or humanize the embedding field label.
     */
    protected function translateEmbeddingLabel(string $label, string $modelBase): string
    {
        $translationKeys = [
            "models.{$modelBase}.{$label}",
            "app.{$label}",
            $label,
        ];

        foreach ($translationKeys as $key) {
            if (\Illuminate\Support\Facades\Lang::has($key)) {
                return __($key);
            }
        }

        return ucfirst(Str::headline($label));
    }

    /**
     * Format the semantic search parts array into a normalized text representation.
     *
     * @param  array<string>  $parts
     */
    protected function formatEmbeddingText(array $parts): ?string
    {
        if (empty($parts)) {
            return null;
        }

        return implode('. ', $parts).'.';
    }
}

<?php

namespace ValerianMemsk\SemanticSearch\Console\Commands;

use Illuminate\Console\Command;
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;
use ValerianMemsk\SemanticSearch\Models\EntityEmbedding;

class StatusCommand extends Command
{
    protected $signature = 'semantic-search:status
                            {model? : Optional: Specific model class or name}';

    protected $description = 'Show embedding status for all models using HasEmbeddings';

    public function handle(): int
    {
        $modelInput = $this->argument('model');
        $models = [];

        if ($modelInput) {
            $resolvedClass = $modelInput;

            if (! class_exists($resolvedClass)) {
                $guessed = 'App\\Models\\'.ltrim($modelInput, '\\');
                if (class_exists($guessed)) {
                    $resolvedClass = $guessed;
                }
            }

            if (! class_exists($resolvedClass)) {
                // Try matching basename against discovered embeddable models.
                $allModels = SemanticSearch::discoverEmbeddableModels();
                foreach ($allModels as $candidate) {
                    if (class_basename($candidate) === ltrim($modelInput, '\\')) {
                        $resolvedClass = $candidate;
                        break;
                    }
                }
            }

            if (! class_exists($resolvedClass)) {
                $this->error("Could not resolve model class for '{$modelInput}'.");

                return 1;
            }

            if (! SemanticSearch::isEmbeddable($resolvedClass)) {
                $this->error("'{$resolvedClass}' does not use the HasEmbeddings trait.");

                return 1;
            }

            $models[] = $resolvedClass;
        } else {
            $models = SemanticSearch::discoverEmbeddableModels();

            if (empty($models)) {
                $this->warn('No embeddable models found in the codebase.');

                return 0;
            }
        }

        $embeddingModel = config('semantic-search.models.embedding', EntityEmbedding::class);
        $rows = [];
        $totalModels = 0;
        $totalEmbedded = 0;
        $staleCount = 0;

        foreach ($models as $modelClass) {
            $recordCount = $modelClass::count();
            $embeddingCount = $embeddingModel::where('entity_type', $modelClass)->count();
            $lastEmbedded = $embeddingModel::where('entity_type', $modelClass)
                ->max('updated_at');
            $stale = $recordCount > $embeddingCount;

            $totalModels += $recordCount;
            $totalEmbedded += $embeddingCount;
            if ($stale) {
                $staleCount++;
            }

            $displayName = str_starts_with($modelClass, 'App\\Models\\')
                ? substr($modelClass, 11)
                : $modelClass;

            $rows[] = [
                $displayName,
                (string) $recordCount,
                (string) $embeddingCount,
                $stale ? "<fg=red>{$embeddingCount} / {$recordCount}</>" : "<fg=green>✓</>",
                $lastEmbedded ?? '<fg=gray>never</>',
            ];
        }

        $this->table(
            ['Model', 'Records', 'Embeddings', 'Coverage', 'Last Embedded'],
            $rows
        );

        $this->newLine();
        $this->info("Total: {$totalModels} records, {$totalEmbedded} embeddings across ".count($models).' models');

        if ($staleCount > 0) {
            $this->warn("{$staleCount} model(s) have missing embeddings. Run `semantic-search:embed` to fill them.");
        }

        return 0;
    }
}
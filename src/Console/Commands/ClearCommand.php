<?php

namespace ValerianMemsk\SemanticSearch\Console\Commands;

use Illuminate\Console\Command;
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;
use ValerianMemsk\SemanticSearch\Models\EntityEmbedding;

class ClearCommand extends Command
{
    protected $signature = 'semantic-search:clear
                            {model? : Model class or short name to clear embeddings for}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Remove all embeddings for one or all embeddable models';

    public function handle(): int
    {
        $modelInput = $this->argument('model');
        $models = $this->resolveModels($modelInput);

        if ($models === null) {
            return 1;
        }

        if (empty($models)) {
            return 0;
        }

        $embeddingModel = config('semantic-search.models.embedding', EntityEmbedding::class);
        $this->showWhatWillBeCleared($models, $embeddingModel);

        if (! $this->option('force') && ! $this->confirm('Delete these embeddings?', false)) {
            $this->info('Cancelled.');

            return 0;
        }

        $totalDeleted = 0;

        foreach ($models as $modelClass) {
            $deleted = $embeddingModel::where('entity_type', $modelClass)->delete();
            $displayName = $this->displayName($modelClass);
            $this->line("  <fg=green>✓</> {$displayName} — {$deleted} removed");
            $totalDeleted += $deleted;
        }

        $this->newLine();
        $this->info("{$totalDeleted} embeddings cleared across ".count($models).' model(s).');

        return 0;
    }

    /**
     * @return list<class-string>|null  null means error and the caller should exit
     */
    private function resolveModels(?string $modelInput): ?array
    {
        if (! $modelInput) {
            return SemanticSearch::discoverEmbeddableModels();
        }

        $resolvedClass = $modelInput;

        if (! class_exists($resolvedClass)) {
            $guessed = 'App\\Models\\'.ltrim($modelInput, '\\');
            if (class_exists($guessed)) {
                $resolvedClass = $guessed;
            }
        }

        if (! class_exists($resolvedClass)) {
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

            return null;
        }

        if (! SemanticSearch::isEmbeddable($resolvedClass)) {
            $this->error("'{$resolvedClass}' does not use the HasEmbeddings trait.");

            return null;
        }

        return [$resolvedClass];
    }

    private function displayName(string $modelClass): string
    {
        return str_starts_with($modelClass, 'App\\Models\\')
            ? substr($modelClass, 11)
            : $modelClass;
    }

    /**
     * @param  list<class-string>  $models
     */
    private function showWhatWillBeCleared(array $models, string $embeddingModel): void
    {
        $rows = [];
        $total = 0;

        foreach ($models as $modelClass) {
            $count = $embeddingModel::where('entity_type', $modelClass)->count();
            $total += $count;
            $rows[] = [$this->displayName($modelClass), (string) $count];
        }

        $this->table(['Model', 'Embeddings'], $rows);
        $this->newLine();
        $this->warn("About to delete {$total} embedding(s).");
    }
}
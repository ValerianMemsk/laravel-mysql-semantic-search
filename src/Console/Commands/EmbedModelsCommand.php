<?php

namespace ValerianMemsk\SemanticSearch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;

class EmbedModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semantic-search:embed
                            {model? : Optional: Specific model class or name (e.g. App\\Models\\Document)}
                            {--driver= : Specific embedding driver to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk generate semantic vector embeddings for all existing records of searchable models';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelInput = $this->argument('model');
        $driver = $this->option('driver');
        $modelsToProcess = [];

        if ($modelInput) {
            $resolvedClass = $modelInput;

            if (! class_exists($resolvedClass)) {
                // Try resolving via common model namespace
                $guessed = 'App\\Models\\'.ltrim($modelInput, '\\');
                if (class_exists($guessed)) {
                    $resolvedClass = $guessed;
                }
            }

            if (! class_exists($resolvedClass)) {
                $this->error("Could not resolve model class for '{$modelInput}'.");

                return 1;
            }

            if (! SemanticSearch::isEmbeddable($resolvedClass)) {
                $this->error("The model '{$resolvedClass}' does not support embeddings (does not use HasEmbeddings trait).");

                return 1;
            }

            $modelsToProcess[] = $resolvedClass;
        } else {
            $this->info('Scanning codebase for embeddable models...');
            $modelsToProcess = SemanticSearch::discoverEmbeddableModels();

            if (empty($modelsToProcess)) {
                $this->warn('No embeddable models (using HasEmbeddings trait) were found.');

                return 0;
            }

            $this->info('Discovered embeddable models: '.implode(', ', array_map(fn ($m) => class_basename($m), $modelsToProcess)));
        }

        $totalErrors = 0;

        foreach ($modelsToProcess as $modelClass) {
            $count = $modelClass::count();

            if ($count === 0) {
                $this->warn("No records found for '".class_basename($modelClass)."' in the database.");

                continue;
            }

            $this->newLine();
            $this->info("Processing '".class_basename($modelClass)."' (Total: {$count} records)...");

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $errors = 0;

            // Process in chunks to prevent memory consumption and connection timeout issues
            $modelClass::chunkById(100, function ($records) use ($bar, &$errors, $driver) {
                foreach ($records as $record) {
                    try {
                        SemanticSearch::embedModel($record, $driver);
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->newLine();
                        $this->error("Failed to generate embedding for ID {$record->getKey()}: ".$e->getMessage());
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();

            if ($errors > 0) {
                $this->warn("Embedding finished for '".class_basename($modelClass)."' with {$errors} errors.");
                $totalErrors += $errors;
            } else {
                $this->info("Successfully embedded all {$count} records of '".class_basename($modelClass)."'!");
            }
        }

        $this->newLine();
        if ($totalErrors > 0) {
            $this->warn('Bulk embedding completed with some errors across the run.');

            return 1;
        }

        $this->info('All bulk embedding tasks completed successfully!');

        return 0;
    }
}

<?php

namespace ValerianMemsk\SemanticSearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use ValerianMemsk\SemanticSearch\Console\Commands\EmbedModelsCommand;
use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;
use ValerianMemsk\SemanticSearch\Drivers\MariaDbVectorDriver;
use ValerianMemsk\SemanticSearch\Drivers\MySqlVectorDriver;
use ValerianMemsk\SemanticSearch\Embedders\EmbedderManager;
use ValerianMemsk\SemanticSearch\Engines\SemanticSearchEngine;
use ValerianMemsk\SemanticSearch\Services\SemanticSearchService;

class SemanticSearchServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/semantic-search.php',
            'semantic-search'
        );

        $this->app->singleton(VectorDriverContract::class, function ($app) {
            $driver = config('semantic-search.database_driver', 'mariadb');

            return match ($driver) {
                'mysql' => new MySqlVectorDriver,
                default => new MariaDbVectorDriver,
            };
        });

        $this->app->singleton('semantic-search.embedder-manager', function ($app) {
            return new EmbedderManager($app);
        });

        $this->app->singleton('semantic-search', function ($app) {
            return new SemanticSearchService(
                $app->make('semantic-search.embedder-manager'),
                $app->make(VectorDriverContract::class)
            );
        });

        $this->app->alias('semantic-search', SemanticSearchService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerScoutDriver();
    }

    /**
     * Register package publishable assets.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/semantic-search.php' => config_path('semantic-search.php'),
            ], 'semantic-search-config');

            $timestamp = date('Y_m_d_His');
            $this->publishes([
                __DIR__.'/../database/migrations/create_entity_embeddings_table.php.stub' => database_path("migrations/{$timestamp}_create_entity_embeddings_table.php"),
                __DIR__.'/../database/migrations/create_semantic_dictionaries_table.php.stub' => database_path("migrations/{$timestamp}_create_semantic_dictionaries_table.php"),
            ], 'semantic-search-migrations');
        }
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EmbedModelsCommand::class,
            ]);
        }
    }

    /**
     * Register semantic engine driver with Laravel Scout.
     */
    protected function registerScoutDriver(): void
    {
        if (class_exists(EngineManager::class)) {
            try {
                resolve(EngineManager::class)->extend('semantic', function ($app) {
                    $fallbackDriver = config('scout.fallback_driver', 'database');
                    $fallbackEngine = resolve(EngineManager::class)->engine($fallbackDriver);

                    return new SemanticSearchEngine($fallbackEngine);
                });
            } catch (\Throwable $e) {
                // Ignore if Scout is not loaded or during early boot
            }
        }
    }
}

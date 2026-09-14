<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Embedder Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "ollama", "openai", or a custom driver registered
    | via EmbedderManager.
    |
    */
    'default' => env('SEMANTIC_SEARCH_DRIVER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | Vector Database Driver
    |--------------------------------------------------------------------------
    |
    | Defines the SQL vector dialect: "mariadb" (VEC_DISTANCE_COSINE) or
    | "mysql" (VECTOR_DISTANCE for MySQL 9.0+).
    |
    */
    'database_driver' => env('SEMANTIC_SEARCH_DB_DRIVER', 'mariadb'),

    /*
    |--------------------------------------------------------------------------
    | Vector Dimensions
    |--------------------------------------------------------------------------
    |
    | The dimensionality of the vector embeddings produced by the model.
    | Common dimensions:
    | - 1024 (bge-m3, nomic-embed-text-v1.5)
    | - 1536 (OpenAI text-embedding-3-small, text-embedding-ada-002)
    | - 3072 (OpenAI text-embedding-3-large)
    | - 768  (nomic-embed-text, all-mpnet-base-v2)
    |
    */
    'dimensions' => (int) env('SEMANTIC_SEARCH_DIMENSIONS', 1024),

    /*
    |--------------------------------------------------------------------------
    | Embedding Providers & Drivers
    |--------------------------------------------------------------------------
    |
    | Configure options for each supported embedding driver.
    |
    */
    'drivers' => [
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_EMBED_MODEL', 'bge-m3'),
            'timeout' => (int) env('OLLAMA_TIMEOUT', 5),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Distance Threshold
    |--------------------------------------------------------------------------
    |
    | Maximum vector cosine distance threshold for matches.
    | In cosine distance: 0 is identical, higher is further away.
    | Typically values between 0.45 and 0.55 work well.
    |
    */
    'threshold' => (float) env('SEMANTIC_SEARCH_THRESHOLD', 0.49),

    /*
    |--------------------------------------------------------------------------
    | Reciprocal Rank Fusion (RRF)
    |--------------------------------------------------------------------------
    |
    | Tuning parameter 'k' for RRF hybrid search fusion.
    | Standard industry default is 60.
    |
    */
    'rrf' => [
        'k' => (int) env('SEMANTIC_SEARCH_RRF_K', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Queue Dispatch on Model Save
    |--------------------------------------------------------------------------
    |
    | Automatically dispatch embedding generation jobs on Eloquent saved event.
    |
    */
    'auto_embed_on_save' => (bool) env('SEMANTIC_SEARCH_AUTO_EMBED', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | Name of queue to push embedding generation jobs to (null for default).
    |
    */
    'queue' => env('SEMANTIC_SEARCH_QUEUE', null),

    /*
    |--------------------------------------------------------------------------
    | Query Preprocessors
    |--------------------------------------------------------------------------
    |
    | Classes that transform or expand query and document texts before embedding.
    |
    */
    'preprocessors' => [
        \ValerianMemsk\SemanticSearch\Preprocessors\DictionaryPreprocessor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic Dictionary (Synonym / Acronym Expansion)
    |--------------------------------------------------------------------------
    |
    */
    'dictionary' => [
        'enabled' => (bool) env('SEMANTIC_SEARCH_DICTIONARY_ENABLED', true),
        'cache_key' => 'semantic_dictionary',
        'model' => \ValerianMemsk\SemanticSearch\Models\SemanticDictionary::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models & Database Tables
    |--------------------------------------------------------------------------
    */
    'models' => [
        'embedding' => \ValerianMemsk\SemanticSearch\Models\EntityEmbedding::class,
    ],

    'tables' => [
        'embeddings' => 'entity_embeddings',
        'dictionaries' => 'semantic_dictionaries',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery Paths for Artisan Embed Command
    |--------------------------------------------------------------------------
    */
    'discovery_paths' => [
        app_path('Models'),
    ],
];

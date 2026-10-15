# Laravel Semantic Search (MariaDB & MySQL)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/valerianmemsk/laravel-semantic-search.svg?style=flat-square)](https://packagist.org/packages/valerianmemsk/laravel-semantic-search)
[![Total Downloads](https://img.shields.io/packagist/dt/valerianmemsk/laravel-semantic-search.svg?style=flat-square)](https://packagist.org/packages/valerianmemsk/laravel-semantic-search)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

> Experimental, work in progress. This started as functionality I built for a work project and later extracted into a package. It hasn't been exhaustively tested across production environments and edge cases.
>
> If you find architectural flaws, missing tests, or performance problems, please open an issue or send a PR. Honest criticism is welcome.

## Why this package

Adding vector search to Laravel usually means running a separate vector database (Pinecone, Milvus, Qdrant, Weaviate) or PostgreSQL with `pgvector`.

MariaDB 11.7+ and MySQL 9.0+ now have native `VECTOR` columns and distance functions (`VEC_DISTANCE_COSINE`, `VECTOR_DISTANCE`). This package uses those, so you get semantic search inside the database you already run, with no extra infrastructure. It can also combine semantic results with regular keyword search using Reciprocal Rank Fusion (RRF).

## Features

- Hybrid search: merges semantic similarity with keyword search (Scout or SQL `LIKE`) using RRF.
- Native SQL vector storage on MariaDB 11.7+ or MySQL 9.0+.
- Pluggable embedders: Ollama and OpenAI built in, plus any other API or model (Hugging Face, Cohere, Voyage, TEI, ...) through a driver.
- Scout engine: set `SCOUT_DRIVER=semantic` and it falls back to your normal driver when the embedder is unavailable.
- Synonym and acronym expansion: a preprocessor that expands abbreviations and domain terms before embedding. The dictionary is swappable.
- Background ingestion: queued jobs on a model's `saved` event.

## Requirements

| Requirement | Minimum version | Note |
|---|---|---|
| PHP | 8.2+ | |
| Laravel | 12.0 - 13.0 | |
| MariaDB or MySQL | MariaDB 11.7+ or MySQL 9.0+ | Required for the native `VECTOR` type |
| Embedder | Ollama, OpenAI, or a custom API | Ollama/OpenAI built in; anything else via a driver |

## Quick start

### Install

```bash
composer require valerianmemsk/laravel-semantic-search
```

### Publish config and migrations

```bash
php artisan vendor:publish --tag="semantic-search-config"
php artisan vendor:publish --tag="semantic-search-migrations"
php artisan migrate
```

### Configure environment

```env
# Vector database dialect: 'mariadb' or 'mysql'
SEMANTIC_SEARCH_DB_DRIVER=mariadb

# Embedder driver: 'ollama' or 'openai'
SEMANTIC_SEARCH_DRIVER=ollama

# Vector dimensions (1024 for bge-m3, 1536 for text-embedding-3-small)
SEMANTIC_SEARCH_DIMENSIONS=1024

# Ollama settings (if using Ollama)
OLLAMA_URL=http://localhost:11434
OLLAMA_EMBED_MODEL=bge-m3

# OpenAI settings (if using OpenAI)
# OPENAI_API_KEY=sk-...
# OPENAI_EMBED_MODEL=text-embedding-3-small

# Cosine distance threshold (0.0 = exact match, ~0.45 - 0.55 recommended)
SEMANTIC_SEARCH_THRESHOLD=0.49
```

## How to use

### 1. Prepare your model

Add the `HasEmbeddings` trait and define which fields become the embedding:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class Article extends Model
{
    use Searchable, HasEmbeddings;

    public function toSemanticSearchArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => strip_tags($this->body),
        ];
    }
}
```

Saving the model pushes an embedding job to your queue.

### 2. Search via Scout

Set `SCOUT_DRIVER=semantic` in `.env` (or `config/scout.php`):

```php
// config/scout.php
'driver' => env('SCOUT_DRIVER', 'semantic'),
'fallback_driver' => 'database',
```

Search as usual:

```php
$articles = Article::search('how to optimize database queries')->get();
```

Behind the scenes it embeds the query, runs a cosine-distance query, runs a lexical search through the fallback driver, then fuses both with RRF.

### 3. Direct facade usage

```php
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;
use App\Models\Article;

$semanticIds = SemanticSearch::searchIds(Article::class, 'machine learning basics', limit: 20);

$keywordIds = Article::where('title', 'like', '%machine learning%')->pluck('id')->toArray();
$finalRankedIds = SemanticSearch::combineRRF($semanticIds ?? [], $keywordIds);
```

### 4. Bulk-generate embeddings

```bash
# All models using HasEmbeddings
php artisan semantic-search:embed

# A specific model
php artisan semantic-search:embed "App\Models\Article"
```

### 5. Custom embedders

Ollama and OpenAI are built in, but any API or model works (Hugging Face Inference API, Text Embeddings Inference, Cohere, Voyage, a custom Python service, ...).

Implement `ValerianMemsk\SemanticSearch\Contracts\EmbedderContract`:

```php
namespace App\Search\Embedders;

use Illuminate\Support\Facades\Http;
use ValerianMemsk\SemanticSearch\Contracts\EmbedderContract;

class HuggingFaceEmbedder implements EmbedderContract
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'BAAI/bge-m3',
        protected int $dimensions = 1024
    ) {}

    public function embed(string $text): array
    {
        $response = Http::withToken($this->apiKey)
            ->post("https://api-inference.huggingface.co/pipeline/feature-extraction/{$this->model}", [
                'inputs' => $text,
            ]);

        return $response->json();
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
```

Register it in `AppServiceProvider::boot()`:

```php
use ValerianMemsk\SemanticSearch\Embedders\EmbedderManager;
use App\Search\Embedders\HuggingFaceEmbedder;

public function boot(): void
{
    $this->app->make('semantic-search.embedder-manager')->extend('huggingface', function ($app) {
        return new HuggingFaceEmbedder(
            apiKey: config('services.huggingface.key'),
            model: 'BAAI/bge-m3',
            dimensions: 1024
        );
    });
}
```

Then set `SEMANTIC_SEARCH_DRIVER=huggingface` in `.env`.

### 6. Custom synonym dictionary

Synonyms map a term to a replacement (for example `ФЛ` → `физическое лицо`) and are applied to query and document text before embedding. By default they come from the `semantic_dictionaries` table (published migration) through the `SemanticDictionary` model, cached in memory.

To use your own source, bind `DictionarySourceContract`:

```php
use ValerianMemsk\SemanticSearch\Contracts\DictionarySourceContract;

public function register(): void
{
    $this->app->bind(DictionarySourceContract::class, function () {
        return new class implements DictionarySourceContract {
            public function terms(): iterable
            {
                return [
                    (object) ['term' => 'ФЛ', 'replacement' => 'физическое лицо'],
                    (object) ['term' => 'CEO', 'replacement' => 'Chief Executive Officer'],
                ];
            }
        };
    });
}
```

`terms()` can return any iterable of objects with `term` and `replacement` properties.

## Local Ollama quickstart (Docker)

To run a self-hosted embedding model without sending data to external APIs:

```yaml
services:
  ollama:
    image: ollama/ollama:latest
    restart: unless-stopped
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama

  ollama-init:
    image: ollama/ollama:latest
    depends_on:
      - ollama
    environment:
      - OLLAMA_HOST=ollama:11434
    entrypoint:
      - /bin/sh
      - -c
      - |
        until ollama list >/dev/null 2>&1; do sleep 1; done
        ollama pull bge-m3

volumes:
  ollama_data:
```

## Running tests

```bash
composer test
```

No local PHP/Composer? Build the bundled test image and run it in Docker:

```bash
docker build -f Dockerfile.test -t semantic-search-test .
docker run --rm -v "$PWD":/app semantic-search-test composer test
```

## Contributing

This is the first Laravel package I've seriously developed, and it came out of a "can this even be done?" experiment rather than a considered "should it be done?" design. Criticism and contributions are welcome:

- Report bugs and edge cases in the issue tracker.
- Send PRs for new embedder drivers, performance work, or test coverage.
- Tell me how MariaDB/MySQL native vectors behave in real workloads.

## License

MIT. See [LICENSE.md](LICENSE.md).

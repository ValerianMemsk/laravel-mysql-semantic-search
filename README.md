# Laravel Semantic Search (MariaDB & MySQL)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/valerianmemsk/laravel-semantic-search.svg?style=flat-square)](https://packagist.org/packages/valerianmemsk/laravel-semantic-search)
[![Total Downloads](https://img.shields.io/packagist/dt/valerianmemsk/laravel-semantic-search.svg?style=flat-square)](https://packagist.org/packages/valerianmemsk/laravel-semantic-search)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

> ⚠️ **EXPERIMENTAL & WORK-IN-PROGRESS**
> 
> This package was extracted from experimental functionality built for a real-world work project. It is currently in **early active development** and has **not been exhaustively tested** across diverse production environments and edge cases.
> 
> 💬 **Feedback & Criticism Welcome!** If you spot architectural flaws, edge cases, missing tests, or performance bottlenecks, please [open an issue](https://github.com/ValerianMemsk/laravel-semantic-search/issues) or submit a pull request. Honest critiques and ideas are warmly appreciated!

---

## 💡 Why This Package?

Adding vector search to Laravel usually means introducing a separate vector database (like Pinecone, Milvus, Qdrant, or Weaviate) or running PostgreSQL with `pgvector`.

However, recent versions of **MariaDB (11.7+)** and **MySQL (9.0+)** introduced native `VECTOR` datatypes and distance functions (`VEC_DISTANCE_COSINE`, `VECTOR_DISTANCE`).

This package lets you leverage **native SQL vector search** directly inside your existing MySQL or MariaDB database without adding another infrastructure component. It also combines semantic results with standard keyword search using **Reciprocal Rank Fusion (RRF)** for maximum search precision.

---

## ✨ Key Features

- 🧠 **Hybrid Search (RRF)**: Merges semantic vector similarity with traditional keyword search (via Laravel Scout or database `LIKE`) using Reciprocal Rank Fusion.
- 🗄️ **Native SQL Vector Storage**: Uses MariaDB 11.7+ (`VEC_DISTANCE_COSINE` & `VECTOR(N)`) or MySQL 9.0+ (`VECTOR_DISTANCE`).
- 🔌 **Pluggable Embedders**:
  - Local / Self-hosted **Ollama** (`bge-m3`, `nomic-embed-text`, `all-minilm`, etc.)
  - Cloud **OpenAI** (`text-embedding-3-small`, `text-embedding-3-large`)
  - Easily extendable for custom embedding APIs
- ⚡ **Laravel Scout Engine**: Drop-in driver (`SCOUT_DRIVER=semantic`) with automatic fallback to database or Meilisearch if the embedder is unavailable.
- 📖 **Synonym & Acronym Expansion**: Preprocessor with in-memory caching to expand abbreviations and domain-specific terminology before embedding.
- 📦 **Automated Background Ingestion**: Automatically dispatches queued jobs on Eloquent model `saved` events.
- 🖥️ **Filament Plugin**: Optional Filament v3 panel settings page for managing Ollama endpoints, thresholds, and synonym dictionaries.

---

## 📋 Requirements

| Requirement | Minimum Version | Note |
|---|---|---|
| **PHP** | 8.2+ | |
| **Laravel** | 10.0, 11.0, 12.0+ | |
| **MariaDB** or **MySQL** | MariaDB 11.7+ or MySQL 9.0+ | Required for native `VECTOR` type |
| **Embedder** | Ollama or OpenAI | Ollama recommended for self-hosting |

---

## 🚀 Quick Start

### 1. Install via Composer

```bash
composer require valerianmemsk/laravel-semantic-search
```

### 2. Publish Config & Migrations

```bash
php artisan vendor:publish --tag="semantic-search-config"
php artisan vendor:publish --tag="semantic-search-migrations"
php artisan migrate
```

### 3. Configure Environment

Add the following to your `.env` file:

```env
# Vector Database dialect: 'mariadb' or 'mysql'
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

---

## 📖 How to Use

### 1. Prepare Your Eloquent Model

Add the `HasEmbeddings` trait to your model and specify which fields should be transformed into semantic vectors:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class Article extends Model
{
    use Searchable, HasEmbeddings;

    /**
     * Define the data that should be converted into an embedding.
     */
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

Whenever this model is saved, an embedding job will automatically be pushed to your queue.

### 2. Search via Laravel Scout

Set `SCOUT_DRIVER=semantic` in your `.env` (or `config/scout.php`):

```php
// config/scout.php
'driver' => env('SCOUT_DRIVER', 'semantic'),
'fallback_driver' => 'database',
```

Now search just like you normally do with Scout:

```php
$articles = Article::search('how to optimize database queries')->get();
```

Under the hood, this will:
1. Generate an embedding vector for the search query.
2. Query the database using cosine distance.
3. Perform a lexical search using the fallback driver (e.g. database `LIKE`).
4. Fuse both result sets using Reciprocal Rank Fusion (RRF) to return the most relevant models.

### 3. Direct Facade Usage (Without Scout)

You can also use the `SemanticSearch` facade directly:

```php
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;
use App\Models\Article;

// 1. Get matching IDs ordered by semantic similarity
$semanticIds = SemanticSearch::searchIds(Article::class, 'machine learning basics', limit: 20);

// 2. Perform hybrid rank fusion with your own custom SQL search
$keywordIds = Article::where('title', 'like', '%machine learning%')->pluck('id')->toArray();
$finalRankedIds = SemanticSearch::combineRRF($semanticIds ?? [], $keywordIds);
```

### 4. Bulk Generate Embeddings for Existing Data

To generate embeddings for existing records in your database:

```bash
# Scan and embed all models using HasEmbeddings
php artisan semantic-search:embed

# Or embed a specific model
php artisan semantic-search:embed "App\Models\Article"
```

---

## 🐳 Local Ollama Quickstart (Docker)

If you want a local, self-hosted embedding model without sending data to external APIs, add this to your `docker-compose.yml`:

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

---

## 🧩 Filament Admin UI (Optional)

If you use [Filament](https://filamentphp.com), register the plugin in your Panel provider to get a visual settings interface:

```php
use ValerianMemsk\SemanticSearch\Filament\SemanticSearchPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            SemanticSearchPlugin::make()
                ->navigationGroup('Settings')
                ->navigationLabel('Semantic Search')
        );
}
```

---

## 🧪 Running Tests

```bash
composer test
```

---

## 🤝 Contributing & Feedback

Since this package is experimental, all contributions are welcome:
- Report bugs and edge cases in the [Issue Tracker](https://github.com/ValerianMemsk/laravel-semantic-search/issues).
- Submit Pull Requests for new embedder drivers, performance improvements, or test coverage.
- Share your experience using MariaDB/MySQL native vectors in real-world workloads!

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for details.

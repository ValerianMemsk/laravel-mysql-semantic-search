# Laravel Semantic Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/valerianmemsk/laravel-semantic-search.svg?style=flat-square)](https://packagist.org/packages/valerianmemsk/laravel-semantic-search)
[![Total Downloads](https://img.shields.io/packagist/dt/valerianmemsk/laravel-semantic-search.svg?style=flat-square)](https://packagist.org/packages/valerianmemsk/laravel-semantic-search)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

Production-ready **Semantic Vector Search & Hybrid Search (RRF)** for Laravel, powered by MariaDB (11.7+) and MySQL (9.0+) native `VECTOR` datatypes with pluggable embedding drivers (Ollama, OpenAI, or custom).

---

## ✨ Features

- 🧠 **Hybrid Search with Reciprocal Rank Fusion (RRF)**: Merges semantic vector similarity with lexical (SQL / Scout) keyword search to provide the highest relevance.
- 🗄️ **Native SQL Vectors**: Uses MariaDB 11.7+ `VEC_DISTANCE_COSINE` & `VECTOR(N)` or MySQL 9.0+ `VECTOR_DISTANCE`.
- 🔌 **Pluggable Embedders**: Built-in support for local self-hosted **Ollama** (`bge-m3`, `nomic-embed-text`, etc.) and cloud **OpenAI** (`text-embedding-3-small`, `text-embedding-3-large`).
- ⚡ **Laravel Scout Engine**: Seamless integration as a Scout driver (`SCOUT_DRIVER=semantic`) with automatic fallback to database/Meilisearch.
- 📖 **Semantic Synonyms & Acronym Expansion**: In-memory cached dictionary preprocessor to normalize queries and documents (e.g. `ФЛ` → `Физическое лицо`).
- 🛡️ **Resilient & Silent Degradation**: Automatic timeouts and fallback to standard search if the vector embedding service is unreachable.
- 📦 **Automated Background Ingestion**: Dispatches queued jobs on model `saved` event.

---

## 🚀 Installation

Install the package via Composer:

```bash
composer require valerianmemsk/laravel-semantic-search
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag="semantic-search-config"
php artisan vendor:publish --tag="semantic-search-migrations"
php artisan migrate
```

---

## ⚙️ Configuration

The published `config/semantic-search.php` file allows you to customize the driver, dimensions, thresholds, and dictionary:

```php
return [
    // 'ollama' or 'openai'
    'default' => env('SEMANTIC_SEARCH_DRIVER', 'ollama'),

    // 'mariadb' or 'mysql'
    'database_driver' => env('SEMANTIC_SEARCH_DB_DRIVER', 'mariadb'),

    // Dimensionality (1024 for bge-m3, 1536 for OpenAI text-embedding-3-small)
    'dimensions' => (int) env('SEMANTIC_SEARCH_DIMENSIONS', 1024),

    'drivers' => [
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_EMBED_MODEL', 'bge-m3'),
            'timeout' => 5,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
            'timeout' => 10,
        ],
    ],

    // Cosine distance threshold (0 is exact, 0.49 is a good default)
    'threshold' => (float) env('SEMANTIC_SEARCH_THRESHOLD', 0.49),

    'rrf' => [
        'k' => 60,
    ],
];
```

---

## 🐳 Docker Ollama Setup (Optional Quickstart)

To run a zero-configuration local Ollama instance with `bge-m3` embedding model:

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

## 📖 Usage

### 1. Preparing your Model

Add the `HasEmbeddings` trait to any Eloquent model and define searchable attributes:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class Document extends Model
{
    use Searchable, HasEmbeddings;

    /**
     * Specify fields for semantic vector generation.
     */
    public function toSemanticSearchArray(): array
    {
        return [
            'title' => $this->title,
            'content' => strip_tags($this->body),
        ];
    }
}
```

### 2. Searching with Laravel Scout

Configure your `config/scout.php`:

```php
'driver' => env('SCOUT_DRIVER', 'semantic'),
'fallback_driver' => 'database',
```

Then perform searches as usual:

```php
$results = Document::search('annual financial report')->get();
```

### 3. Direct Service & Facade Usage

You can also search IDs or fuse result lists directly:

```php
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;

// 1. Semantic Vector Search
$semanticIds = SemanticSearch::searchIds(Document::class, 'employment contract', limit: 20);

// 2. Reciprocal Rank Fusion (RRF)
$keywordIds = Document::where('title', 'like', '%contract%')->pluck('id')->toArray();
$rankedIds = SemanticSearch::combineRRF($semanticIds ?? [], $keywordIds);
```

### 4. Bulk Embedding Command

Generate vector embeddings for all existing database records:

```bash
# Embed all models
php artisan semantic-search:embed

# Embed a specific model
php artisan semantic-search:embed "App\Models\Document"
```

### 5. Filament Admin Panel Plugin (Optional)

If your project uses [Filament](https://filamentphp.com), you can register the built-in plugin in your Panel provider to get an interactive UI for embedding settings and synonym/acronym management:

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

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Skill: Laravel Semantic Search (MariaDB & MySQL)

## Description
This skill provides instructions, architectural rules, and code patterns for AI agents (OpenCode, Claude, Cursor, Copilot) when installing, configuring, troubleshooting, or integrating the `valerianmemsk/laravel-semantic-search` package in Laravel applications.

---

## 🧠 Architectural Mental Model

This package provides **native SQL vector semantic search** and **hybrid lexical-vector search** using **Reciprocal Rank Fusion (RRF)** directly inside **MariaDB 11.7+** and **MySQL 9.0+** without external vector databases.

### Core Components:
1. **Embedder Drivers** (`EmbedderManager`):
   - `ollama`: Uses local HTTP endpoint (e.g. `http://localhost:11434/api/embeddings`) with models like `bge-m3` (1024d) or `nomic-embed-text` (768d).
   - `openai`: Uses `https://api.openai.com/v1/embeddings` with `text-embedding-3-small` (1536d) or `text-embedding-3-large` (3072d).
2. **Database Vector Drivers** (`VectorDriverContract`):
   - `mariadb`: Uses `VEC_DISTANCE_COSINE(embedding, VEC_FromText(?))` on `VECTOR(N)` columns.
   - `mysql`: Uses `VECTOR_DISTANCE(embedding, STRING_TO_VECTOR(?), 'COSINE')` on `VECTOR(N)` columns.
3. **Hybrid Search (RRF)**:
   - Evaluates vector nearest neighbors + keyword search results (via Scout or SQL `LIKE`).
   - Merges ranks using formula: $Score(d) = \sum \frac{1}{k + rank(d)}$ where $k = 60$.
4. **Model Trait** (`HasEmbeddings`):
   - Listens to model `saved` event to dispatch background job `GenerateModelEmbedding`.
   - Stores vectors in polymorphic table `entity_embeddings`.

---

## 🛠️ Integration Workflow for Agents

When a user asks to add semantic search or integrate this package into an application, follow these precise steps:

### Step 1: Verify Host Prerequisites
Before modifying code, check:
- PHP version $\ge 8.2$.
- Database server is **MariaDB $\ge 11.7$** or **MySQL $\ge 9.0$** (required for native `VECTOR` datatypes).
- An embedding provider is reachable:
  - Local Ollama running with the target model (e.g. `ollama pull bge-m3`), OR
  - OpenAI API key available in `.env`.

### Step 2: Install Package & Publish Assets
Execute in bash:
```bash
composer require valerianmemsk/laravel-semantic-search
php artisan vendor:publish --tag="semantic-search-config"
php artisan vendor:publish --tag="semantic-search-migrations"
php artisan migrate
```

### Step 3: Configure Environment Variables
Set the correct dimensions according to the model used in `.env`:
```env
SEMANTIC_SEARCH_DB_DRIVER=mariadb # or mysql
SEMANTIC_SEARCH_DRIVER=ollama     # or openai
SEMANTIC_SEARCH_DIMENSIONS=1024   # 1024 for bge-m3, 1536 for OpenAI text-embedding-3-small
SEMANTIC_SEARCH_THRESHOLD=0.49    # Cosine distance cutoff (0.0 to 1.0)

# If using Ollama:
OLLAMA_URL=http://localhost:11434
OLLAMA_EMBED_MODEL=bge-m3

# If using OpenAI:
# OPENAI_API_KEY=sk-...
# OPENAI_EMBED_MODEL=text-embedding-3-small
```

### Step 4: Instrument Eloquent Models
Attach `HasEmbeddings` trait and define `toSemanticSearchArray()`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class Product extends Model
{
    use Searchable, HasEmbeddings;

    /**
     * Define the data that should be converted into an embedding.
     * Return associative array of text attributes.
     */
    public function toSemanticSearchArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category?->name,
            'description' => strip_tags($this->description ?? ''),
            'sku' => $this->sku,
        ];
    }
}
```

### Step 5: Configure Laravel Scout (Optional but Recommended)
If Scout is used:
In `.env`:
```env
SCOUT_DRIVER=semantic
```
In `config/scout.php`:
```php
'driver' => env('SCOUT_DRIVER', 'semantic'),
'fallback_driver' => 'database',
```

### Step 6: Initial Embedding Generation
Generate embeddings for existing records:
```bash
php artisan semantic-search:embed "App\Models\Product"
```

---

## 💻 Direct Programmatic Usage Patterns

### Pattern A: Direct Semantic Search via Facade
```php
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;
use App\Models\Product;

// Returns array of primary keys (int[] or string[]) matching query within distance threshold
$productIds = SemanticSearch::searchIds(Product::class, 'ergonomic mesh office chair', limit: 25);

if (!empty($productIds)) {
    // Maintain order by using FIELD() or PHP sorting
    $products = Product::whereIn('id', $productIds)
        ->orderByRaw('FIELD(id, ' . implode(',', $productIds) . ')')
        ->get();
}
```

### Pattern B: Custom Hybrid Fusion (RRF)
```php
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;
use App\Models\Product;

$query = 'gaming laptop';

// 1. Semantic IDs
$semanticIds = SemanticSearch::searchIds(Product::class, $query, limit: 50) ?? [];

// 2. Keyword IDs (e.g. standard SQL LIKE or FullText)
$keywordIds = Product::where('name', 'like', "%{$query}%")
    ->orWhere('description', 'like', "%{$query}%")
    ->limit(50)
    ->pluck('id')
    ->toArray();

// 3. Merge and rerank using RRF
$rankedIds = SemanticSearch::combineRRF($semanticIds, $keywordIds, k: 60);

// 4. Fetch records in ranked order
$results = Product::whereIn('id', array_slice($rankedIds, 0, 20))->get()
    ->sortBy(fn ($p) => array_search($p->id, $rankedIds))
    ->values();
```

---

## 🩺 Troubleshooting & Diagnostics for Agents

1. **`Distance Threshold` Returning No Results**:
   - Cosine distance in MariaDB/MySQL is `0.0` for identical vectors and approaches `1.0` (or `2.0`) for orthogonal/opposite vectors.
   - If search returns 0 results for reasonable queries, check if `SEMANTIC_SEARCH_THRESHOLD` is set too strict (e.g. `< 0.35`). Start with `0.55` and adjust down.

2. **Vector Dimension Mismatch**:
   - Error: `VEC_DISTANCE_COSINE argument dimensionality mismatch`.
   - Ensure the database column dimension in migration (`SEMANTIC_SEARCH_DIMENSIONS`) exactly matches the output vector dimension of the chosen model (`1024` for `bge-m3`, `1536` for `text-embedding-3-small`, `768` for `nomic-embed-text`).
   - If model changes, the `entity_embeddings` table must be migrated / rebuilt.

3. **Ollama Timeout or Unreachable**:
   - The package silently fails over to the Scout fallback driver or returns `null` if Ollama times out.
   - Test connectivity: `curl -s http://localhost:11434/api/tags` to ensure the daemon is running and model is loaded.

4. **Binary Unpack Format on Retrieve**:
   - MariaDB stores vectors as raw binary sequences of 32-bit little-endian IEEE floats.
   - `VectorCast` handles conversion via `unpack('f*', $value)`. If retrieving via raw SQL queries, remember to use `VEC_AsText(embedding)` or the `EntityEmbedding` Eloquent model.

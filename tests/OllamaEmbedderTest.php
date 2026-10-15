<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use Exception;
use Illuminate\Support\Facades\Http;
use ValerianMemsk\SemanticSearch\Embedders\OllamaEmbedder;

class OllamaEmbedderTest extends TestCase
{
    public function test_embed_returns_embedding_on_success(): void
    {
        Http::fake([
            'http://localhost:11434/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]], 200),
        ]);

        $embedder = new OllamaEmbedder([
            'url' => 'http://localhost:11434',
            'model' => 'bge-m3',
        ]);

        $this->assertSame([0.1, 0.2, 0.3], $embedder->embed('hello world'));
    }

    public function test_embed_throws_on_http_error(): void
    {
        Http::fake([
            'http://localhost:11434/api/embeddings' => Http::response('oops', 500),
        ]);

        $embedder = new OllamaEmbedder([
            'url' => 'http://localhost:11434',
            'model' => 'bge-m3',
        ]);

        $this->expectException(Exception::class);
        $embedder->embed('hello world');
    }

    public function test_dimensions_returns_configured_value(): void
    {
        $embedder = new OllamaEmbedder(['url' => 'u', 'model' => 'm', 'dimensions' => 768]);

        $this->assertSame(768, $embedder->dimensions());
    }
}

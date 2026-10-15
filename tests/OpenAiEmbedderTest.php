<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use Exception;
use Illuminate\Support\Facades\Http;
use ValerianMemsk\SemanticSearch\Embedders\OpenAiEmbedder;

class OpenAiEmbedderTest extends TestCase
{
    public function test_embed_returns_embedding_on_success(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [0.1, 0.2]]]], 200),
        ]);

        $embedder = new OpenAiEmbedder(['api_key' => 'sk-test', 'model' => 'text-embedding-3-small']);

        $this->assertSame([0.1, 0.2], $embedder->embed('hello'));
    }

    public function test_embed_throws_when_api_key_missing(): void
    {
        $embedder = new OpenAiEmbedder(['api_key' => null, 'model' => 'text-embedding-3-small']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API key is not configured');
        $embedder->embed('hello');
    }

    public function test_embed_throws_on_http_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response('unauthorized', 401),
        ]);

        $embedder = new OpenAiEmbedder(['api_key' => 'sk-test', 'model' => 'text-embedding-3-small']);

        $this->expectException(Exception::class);
        $embedder->embed('hello');
    }
}

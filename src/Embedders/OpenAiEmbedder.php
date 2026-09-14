<?php

namespace ValerianMemsk\SemanticSearch\Embedders;

use Exception;
use Illuminate\Support\Facades\Http;
use ValerianMemsk\SemanticSearch\Contracts\EmbedderContract;

class OpenAiEmbedder implements EmbedderContract
{
    protected ?string $apiKey;
    protected string $model;
    protected int $timeout;
    protected int $dimensions;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? config('semantic-search.drivers.openai.api_key');
        $this->model = $config['model'] ?? config('semantic-search.drivers.openai.model', 'text-embedding-3-small');
        $this->timeout = (int) ($config['timeout'] ?? config('semantic-search.drivers.openai.timeout', 10));
        $this->dimensions = (int) ($config['dimensions'] ?? config('semantic-search.dimensions', 1536));
    }

    /**
     * Generate vector embeddings via OpenAI API.
     *
     * @throws Exception
     */
    public function embed(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is not configured for Semantic Search.');
        }

        $payload = [
            'model' => $this->model,
            'input' => $text,
        ];

        // If dimensions specified and supported
        if ($this->dimensions && str_contains($this->model, 'text-embedding-3')) {
            $payload['dimensions'] = $this->dimensions;
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/embeddings', $payload);

        if ($response->successful()) {
            $data = $response->json('data.0.embedding', []);
            if (! empty($data)) {
                return $data;
            }
        }

        throw new Exception(
            "OpenAI embedding request failed [Status: {$response->status()}]: ".$response->body()
        );
    }

    /**
     * Get vector dimensionality.
     */
    public function dimensions(): int
    {
        return $this->dimensions;
    }
}

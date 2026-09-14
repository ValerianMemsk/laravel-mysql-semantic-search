<?php

namespace ValerianMemsk\SemanticSearch\Embedders;

use Exception;
use Illuminate\Support\Facades\Http;
use ValerianMemsk\SemanticSearch\Contracts\EmbedderContract;

class OllamaEmbedder implements EmbedderContract
{
    protected string $url;
    protected string $model;
    protected int $timeout;
    protected int $dimensions;

    public function __construct(array $config = [])
    {
        $this->url = rtrim($config['url'] ?? config('semantic-search.drivers.ollama.url', 'http://localhost:11434'), '/');
        $this->model = $config['model'] ?? config('semantic-search.drivers.ollama.model', 'bge-m3');
        $this->timeout = (int) ($config['timeout'] ?? config('semantic-search.drivers.ollama.timeout', 5));
        $this->dimensions = (int) ($config['dimensions'] ?? config('semantic-search.dimensions', 1024));
    }

    public function getUrl(): string
    {
        if (class_exists('App\Facades\Config')) {
            $dynamic = \App\Facades\Config::get('search.semantic.ollama.url');
            if (! empty($dynamic)) {
                return rtrim($dynamic, '/');
            }
        }

        return rtrim($this->url ?? config('semantic-search.drivers.ollama.url', 'http://localhost:11434'), '/');
    }

    public function getModel(): string
    {
        if (class_exists('App\Facades\Config')) {
            $dynamic = \App\Facades\Config::get('search.semantic.ollama.model');
            if (! empty($dynamic)) {
                return $dynamic;
            }
        }

        return $this->model ?? config('semantic-search.drivers.ollama.model', 'bge-m3');
    }

    /**
     * Generate vector embeddings via Ollama API.
     *
     * @throws Exception
     */
    public function embed(string $text): array
    {
        $url = $this->getUrl();
        $model = $this->getModel();
        $endpoint = "{$url}/api/embeddings";

        $response = Http::timeout($this->timeout)->post($endpoint, [
            'model' => $model,
            'prompt' => $text,
        ]);

        if ($response->successful()) {
            $embedding = $response->json('embedding', []);
            if (! empty($embedding)) {
                return $embedding;
            }
        }

        throw new Exception(
            "Ollama embedding request failed [Status: {$response->status()}]: ".$response->body()
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

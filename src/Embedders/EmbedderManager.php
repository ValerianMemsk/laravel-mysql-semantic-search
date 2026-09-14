<?php

namespace ValerianMemsk\SemanticSearch\Embedders;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use ValerianMemsk\SemanticSearch\Contracts\EmbedderContract;

class EmbedderManager extends Manager
{
    /**
     * Get the default embedder driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('semantic-search.default', 'ollama');
    }

    /**
     * Create an instance of the Ollama embedder driver.
     */
    public function createOllamaDriver(): EmbedderContract
    {
        $config = $this->config->get('semantic-search.drivers.ollama', []);

        return new OllamaEmbedder($config);
    }

    /**
     * Create an instance of the OpenAI embedder driver.
     */
    public function createOpenaiDriver(): EmbedderContract
    {
        $config = $this->config->get('semantic-search.drivers.openai', []);

        return new OpenAiEmbedder($config);
    }
}

<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use ValerianMemsk\SemanticSearch\Embedders\OllamaEmbedder;
use ValerianMemsk\SemanticSearch\Embedders\OpenAiEmbedder;

class EmbedderManagerTest extends TestCase
{
    public function test_ollama_driver_resolves_to_ollama_embedder(): void
    {
        $manager = app('semantic-search.embedder-manager');

        $this->assertInstanceOf(OllamaEmbedder::class, $manager->driver('ollama'));
    }

    public function test_openai_driver_resolves_to_openai_embedder(): void
    {
        $manager = app('semantic-search.embedder-manager');

        $this->assertInstanceOf(OpenAiEmbedder::class, $manager->driver('openai'));
    }

    public function test_default_driver_is_ollama(): void
    {
        $manager = app('semantic-search.embedder-manager');

        $this->assertInstanceOf(OllamaEmbedder::class, $manager->driver());
    }

    public function test_custom_driver_can_be_extended(): void
    {
        $manager = app('semantic-search.embedder-manager');
        $manager->extend('fake', fn () => new OllamaEmbedder(['url' => 'http://x', 'model' => 'm']));

        $this->assertInstanceOf(OllamaEmbedder::class, $manager->driver('fake'));
    }
}

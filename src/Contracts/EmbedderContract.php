<?php

namespace ValerianMemsk\SemanticSearch\Contracts;

interface EmbedderContract
{
    /**
     * Generate vector embedding for the given text.
     *
     * @return array<float>
     */
    public function embed(string $text): array;

    /**
     * Get the vector dimensionality for this embedder.
     */
    public function dimensions(): int;
}

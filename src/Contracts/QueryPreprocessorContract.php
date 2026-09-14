<?php

namespace ValerianMemsk\SemanticSearch\Contracts;

interface QueryPreprocessorContract
{
    /**
     * Preprocess the given query or document text before embedding.
     */
    public function preprocess(string $text): string;
}

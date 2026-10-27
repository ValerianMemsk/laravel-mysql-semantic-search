<?php

namespace ValerianMemsk\SemanticSearch\Data;

class SearchResult
{
    /**
     * @param  array<int|string>  $ids
     */
    public function __construct(
        public readonly array $ids,
        public readonly bool $success,
        public readonly ?string $error = null,
    ) {}

    public static function ok(array $ids): self
    {
        return new self($ids, true);
    }

    public static function fail(string $error): self
    {
        return new self([], false, $error);
    }
}
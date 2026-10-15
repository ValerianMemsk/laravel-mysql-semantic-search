<?php

namespace ValerianMemsk\SemanticSearch\Contracts;

/**
 * A source of synonym (term -> replacement) mappings used to expand
 * query/document text before embedding.
 */
interface DictionarySourceContract
{
    /**
     * Return all term -> replacement pairs.
     *
     * @return iterable<object{term: string, replacement: string}>
     */
    public function terms(): iterable;
}

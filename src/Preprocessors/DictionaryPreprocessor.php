<?php

namespace ValerianMemsk\SemanticSearch\Preprocessors;

use Exception;
use Illuminate\Support\Facades\Log;
use ValerianMemsk\SemanticSearch\Contracts\DictionarySourceContract;
use ValerianMemsk\SemanticSearch\Contracts\QueryPreprocessorContract;

class DictionaryPreprocessor implements QueryPreprocessorContract
{
    /**
     * Preprocess the given query or document text using the configured semantic dictionary.
     */
    public function preprocess(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        if (! config('semantic-search.dictionary.enabled', true)) {
            return $text;
        }

        try {
            /** @var DictionarySourceContract $source */
            $source = app(DictionarySourceContract::class);

            return $this->replaceTerms($text, $source->terms());
        } catch (Exception $e) {
            Log::warning('[Semantic Search] Dictionary preprocessing failed: '.$e->getMessage());

            return $text;
        }
    }

    /**
     * Replace every dictionary term in a single regex pass.
     *
     * @param  iterable<object{term: ?string, replacement: ?string}>  $dictionaries
     */
    public function replaceTerms(string $text, iterable $dictionaries): string
    {
        $terms = [];
        $replacements = [];

        foreach ($dictionaries as $dict) {
            $term = trim((string) ($dict->term ?? ''));
            $replacement = trim((string) ($dict->replacement ?? ''));

            if ($term === '' || $replacement === '') {
                continue;
            }

            $terms[] = $term;
            $replacements[] = $replacement;
        }

        if (empty($terms)) {
            return $text;
        }

        $termsOrdered = array_map('strlen', $terms);

        // Longest terms first so longer matches win over shorter substrings.
        array_multisort($termsOrdered, SORT_DESC, $terms, $replacements);

        $groups = array_map(fn ($term) => '('.preg_quote($term, '/').')', $terms);
        $pattern = '/(?<!\p{L})(?:'.implode('|', $groups).')(?!\p{L})/iu';

        return preg_replace_callback($pattern, function (array $m) use ($replacements): string {
            // Find which capturing group matched; its index maps to the replacement.
            // Case-insensitive matching is handled by the /iu flags, so no
            // mbstring case-folding is needed here.
            foreach ($replacements as $i => $replacement) {
                if (isset($m[$i + 1]) && $m[$i + 1] !== '') {
                    return $replacement;
                }
            }

            return $m[0];
        }, $text);
    }
}

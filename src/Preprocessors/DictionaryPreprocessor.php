<?php

namespace ValerianMemsk\SemanticSearch\Preprocessors;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ValerianMemsk\SemanticSearch\Contracts\QueryPreprocessorContract;
use ValerianMemsk\SemanticSearch\Models\SemanticDictionary;

class DictionaryPreprocessor implements QueryPreprocessorContract
{
    /**
     * Preprocess the given query or document text using configured semantic dictionary.
     */
    public function preprocess(string $text): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        if (! config('semantic-search.dictionary.enabled', true)) {
            return $text;
        }

        try {
            $cacheKey = config('semantic-search.dictionary.cache_key', 'semantic_dictionary');
            $dictionaryModel = config('semantic-search.dictionary.model', SemanticDictionary::class);

            if (! class_exists($dictionaryModel)) {
                return $text;
            }

            $dictionaries = Cache::rememberForever($cacheKey, function () use ($dictionaryModel) {
                return $dictionaryModel::all(['term', 'replacement']);
            });

            foreach ($dictionaries as $dict) {
                $term = trim($dict->term ?? '');
                $replacement = trim($dict->replacement ?? '');

                if ($term === '') {
                    continue;
                }

                // Match whole Unicode words (Cyrillic + Latin + numerals)
                $pattern = '/(?<!\p{L})'.preg_quote($term, '/').'(?!\p{L})/iu';
                $text = preg_replace($pattern, $replacement, $text);
            }
        } catch (Exception $e) {
            Log::warning('[Semantic Search] Dictionary preprocessing failed: '.$e->getMessage());
        }

        return $text;
    }
}

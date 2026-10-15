<?php

namespace ValerianMemsk\SemanticSearch\Dictionaries;

use Illuminate\Support\Facades\Cache;
use ValerianMemsk\SemanticSearch\Contracts\DictionarySourceContract;
use ValerianMemsk\SemanticSearch\Models\SemanticDictionary;

class DatabaseDictionary implements DictionarySourceContract
{
    public function terms(): iterable
    {
        $cacheKey = config('semantic-search.dictionary.cache_key', 'semantic_dictionary');
        $model = config('semantic-search.dictionary.model', SemanticDictionary::class);

        if (! class_exists($model)) {
            return [];
        }

        return Cache::rememberForever($cacheKey, function () use ($model) {
            return $model::all(['term', 'replacement']);
        });
    }
}

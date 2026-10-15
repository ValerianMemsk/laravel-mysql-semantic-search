<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use ValerianMemsk\SemanticSearch\Contracts\DictionarySourceContract;
use ValerianMemsk\SemanticSearch\Preprocessors\DictionaryPreprocessor;

class DictionaryPreprocessorTest extends TestCase
{
    public function test_replaces_terms_case_insensitively(): void
    {
        $preprocessor = new DictionaryPreprocessor;

        $result = $preprocessor->replaceTerms('The CEO is here', [
            (object) ['term' => 'CEO', 'replacement' => 'Chief Executive Officer'],
        ]);

        $this->assertSame('The Chief Executive Officer is here', $result);
    }

    public function test_respects_word_boundaries(): void
    {
        $preprocessor = new DictionaryPreprocessor;

        $result = $preprocessor->replaceTerms('CEO and ceoforum', [
            (object) ['term' => 'CEO', 'replacement' => 'Chief Executive Officer'],
        ]);

        $this->assertSame('Chief Executive Officer and ceoforum', $result);
    }

    public function test_longest_match_wins(): void
    {
        $preprocessor = new DictionaryPreprocessor;

        $result = $preprocessor->replaceTerms('SQL vs MySQL', [
            (object) ['term' => 'SQL', 'replacement' => 'Structured Query Language'],
            (object) ['term' => 'MySQL', 'replacement' => 'My Structured Query Language'],
        ]);

        $this->assertSame('Structured Query Language vs My Structured Query Language', $result);
    }

    public function test_returns_text_unchanged_when_no_terms(): void
    {
        $preprocessor = new DictionaryPreprocessor;

        $this->assertSame('hello', $preprocessor->replaceTerms('hello', []));
    }

    public function test_preprocess_uses_a_custom_dictionary_source(): void
    {
        app()->instance(DictionarySourceContract::class, new class implements DictionarySourceContract {
            public function terms(): iterable
            {
                yield (object) ['term' => 'ФЛ', 'replacement' => 'физическое лицо'];
            }
        });

        $preprocessor = app(DictionaryPreprocessor::class);

        $this->assertSame('физическое лицо', $preprocessor->preprocess('ФЛ'));
    }
}

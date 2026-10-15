<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use Illuminate\Database\Eloquent\Model;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class HasEmbeddingsTest extends TestCase
{
    public function test_to_searchable_text_builds_from_semantic_search_array(): void
    {
        $model = new SearchableArticle(['title' => 'Hello World', 'body' => '<p>Nice body</p>']);

        $text = $model->toSearchableText();

        $this->assertStringContainsString('Title: Hello World', $text);
        $this->assertStringContainsString('Body: Nice body', $text);
    }

    public function test_to_searchable_text_returns_null_when_no_searchable_source(): void
    {
        $model = new EmptySearchableModel;

        $this->assertNull($model->toSearchableText());
    }
}

class SearchableArticle extends Model
{
    use HasEmbeddings;

    protected $guarded = [];

    public function toSemanticSearchArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}

class EmptySearchableModel extends Model
{
    use HasEmbeddings;
}

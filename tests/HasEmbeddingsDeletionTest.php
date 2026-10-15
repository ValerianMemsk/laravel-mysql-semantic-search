<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ValerianMemsk\SemanticSearch\Models\EntityEmbedding;
use ValerianMemsk\SemanticSearch\Traits\HasEmbeddings;

class HasEmbeddingsDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('entity_embeddings', function ($table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->text('embedding');
            $table->timestamps();
            $table->unique(['entity_type', 'entity_id']);
        });

        Schema::create('articles', function ($table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('entity_embeddings');
        Schema::dropIfExists('articles');

        parent::tearDown();
    }

    public function test_deleting_model_removes_its_embedding(): void
    {
        $article = Article::create(['title' => 'Hello']);
        $article->embedding()->create(['embedding' => '[0.1,0.2]']);

        $this->assertSame(1, EntityEmbedding::where('entity_id', $article->id)->count());

        $article->delete();

        $this->assertSame(0, EntityEmbedding::where('entity_id', $article->id)->count());
    }
}

class Article extends Model
{
    use HasEmbeddings;

    protected $guarded = [];
}

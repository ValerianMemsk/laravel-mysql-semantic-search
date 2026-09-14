<?php

namespace ValerianMemsk\SemanticSearch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ValerianMemsk\SemanticSearch\Casts\VectorCast;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property array<float> $embedding
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EntityEmbedding extends Model
{
    public function getTable()
    {
        return config('semantic-search.tables.embeddings', 'entity_embeddings');
    }

    protected $fillable = [
        'entity_type',
        'entity_id',
        'embedding',
    ];

    protected $casts = [
        'embedding' => VectorCast::class,
    ];

    /**
     * Get the parent embeddable model.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}

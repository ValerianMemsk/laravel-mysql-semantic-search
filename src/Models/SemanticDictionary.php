<?php

namespace ValerianMemsk\SemanticSearch\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $term
 * @property string $replacement
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SemanticDictionary extends Model
{
    public function getTable()
    {
        return config('semantic-search.tables.dictionaries', 'semantic_dictionaries');
    }

    protected $fillable = [
        'term',
        'replacement',
    ];
}

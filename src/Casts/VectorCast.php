<?php

namespace ValerianMemsk\SemanticSearch\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;

class VectorCast implements CastsAttributes
{
    /**
     * Cast the given database value to PHP array.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        /** @var VectorDriverContract $driver */
        $driver = app(VectorDriverContract::class);

        return $driver->parseFromStorage($value);
    }

    /**
     * Prepare the given value for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            /** @var VectorDriverContract $driver */
            $driver = app(VectorDriverContract::class);

            return $driver->prepareForStorage($value);
        }

        return $value;
    }
}

<?php

namespace EQ\LaravelEcommerce\Traits;

use EQ\LaravelEcommerce\Models\Plu;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasPlu
{
    public function plus(): BelongsToMany
    {
        return $this->morphToMany(
            Plu::class,
            'model',
            'model_has_plus',
            'model_id',
            'plu_id',
        );
    }
}

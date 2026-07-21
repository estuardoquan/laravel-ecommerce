<?php

namespace EQ\LaravelEcommerce\Traits;

use EQ\LaravelEcommerce\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait MorphAddresses
{
    public function addresses(): MorphMany
    {
        return $this->morphMany(
            Address::class,
            'model',
            'model_type',
            'model_id',
        );
    }
}

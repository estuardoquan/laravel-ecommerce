<?php

namespace EQ\LaravelEcommerce\Traits;

use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasAddreses
{
    public function addresses(): BelongsToMany
    {
        return $this->morphToMany(
            Address::class,
            'model',
            'model_has_addresses',
            'model_id',
            'address_id',
        );
    }
}

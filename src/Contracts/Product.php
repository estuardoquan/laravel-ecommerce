<?php

namespace EQ\LaravelEcommerce\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|string $id
 * @property int $active
 * @property string $description
 * @property int $multiplier
 * @property string $name
 * @property string $slug
 *
 * @mixin \EQ\LaravelEcommerce\Models\Product
 */
interface Product
{
    public function productVariants(): HasMany;
}

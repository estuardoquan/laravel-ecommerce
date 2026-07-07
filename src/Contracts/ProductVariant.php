<?php

namespace EQ\LaravelEcommerce\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|string $id
 * @property string $key
 * @property string $value
 *
 * @mixin \EQ\LaravelEcommerce\Models\ProductVariant
 */
interface ProductVariant
{
    public function product(): BelongsTo;
}

<?php

namespace EQ\LaravelEcommerce\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int|string $id
 * @property float $price
 * @property string $sku
 * @property string $slug
 * @property int $stock
 *
 * @mixin \EQ\LaravelEcommerce\Models\Plu
 */
interface Plu
{
    public function products(): MorphToMany;

    public function productVariants(): MorphToMany;
}

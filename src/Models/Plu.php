<?php

namespace EQ\LaravelEcommerce\Models;

use EQ\LaravelEcommerce\Contracts\Plu as PluContract;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Plu extends Model implements PluContract
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'price',
        'sku',
        'slug',
        'stock',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'stock' => 'integer'
        ];
    }

    // public function orderDetails()
    // {
    //     return $this->morphedByMany(
    //         OrderDetail::class,
    //         'model',
    //         'model_has_plus',
    //         'plu_id',
    //         'model_id',
    //     );
    // }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(
            Product::class,
            'model',
            'model_has_plus',
            'plu_id',
            'model_id',
        );
    }

    public function productVariants(): MorphToMany
    {
        return $this->morphedByMany(
            ProductVariant::class,
            'model',
            'model_has_plus',
            'plu_id',
            'model_id',
        );
    }

    #[Scope]
    public function whereHasVariantsKeyValue(Builder $query, $value)
    {
        foreach ($value as $k => $v) {
            $query->whereHas(
                'productVariants',
                function (Builder $q) use ($k, $v) {
                    $q->where('key', '=', $k)->where('value', '=', $v);
                }
            );
        }

        return $query;
    }
}

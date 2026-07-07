<?php

namespace EQ\LaravelEcommerce\Models;

use EQ\LaravelEcommerce\Traits\HasPlu;
use EQ\LaravelEcommerce\Contracts\Product as ProductContract;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model implements ProductContract
{
    use HasPlu;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'active',
        'description',
        'multiplier',
        'name',
        'slug',
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
            'active' => 'boolean',
            'bundle' => 'boolean',
            'multiplier' => 'integer',
        ];
    }

    // public function category()
    // {
    //     return $this->belongsTo(
    //         Category::class,
    //         'category_id',
    //         'id'
    //     );
    // }

    // public function subcategory()
    // {
    //     return $this->belongsTo(
    //         Subcategory::class,
    //         'subcategory_id',
    //         'id'
    //     );
    // }

    // public function provider()
    // {
    //     return $this->belongsTo(
    //         Provider::class,
    //         'provider_id',
    //         'id'
    //     );
    // }

    public function productVariants(): HasMany
    {
        return $this->hasMany(
            ProductVariant::class,
            'product_id',
            'id'
        );
    }

    #[Scope]
    public function whereNameLike(Builder $query, $value)
    {
        $v = '%' . $value . '%';

        return $query->where('name', 'like', $v);
    }
}

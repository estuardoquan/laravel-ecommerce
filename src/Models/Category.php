<?php

namespace EQ\LaravelEcommerce\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'active',
        'name',
        'description',
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
        ];
    }

    public function products()
    {
        return $this->hasMany(
            Product::class,
            'category_id',
            'id'
        );
    }

    public function subcategories()
    {
        return $this->hasMany(
            Subcategory::class,
            'category_id',
            'id'
        );
    }

    public function department()
    {
        return $this->belongsTo(
            Department::class,
            'department_id',
            'id'
        );
    }
}

<?php

namespace EQ\LaravelEcommerce\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
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

    public function categories()
    {
        return $this->hasMany(
            Category::class,
            'department_id',
            'id'
        );
    }

    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            Category::class,
            'department_id',
            'category_id',
            'id',
            'id',
        );
    }
}

<?php

namespace App\Models;

use App\Traits\HasPlu;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    /** @use HasFactory<\Database\Factories\OrderDetailsFactory> */
    use HasFactory,
        HasPlu,
        HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quantity'
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
            'quantity' => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(
            Order::class,
            'order_id',
            'id',
        );
    }

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id'
        );
    }

    // public function user()
    // {
    //     return $this->hasOneThrough(
    //         User::class,
    //         Order::class,
    //         'id',
    //         'id',
    //         'order_id',
    //         'user_id'
    //     );
    // }
}

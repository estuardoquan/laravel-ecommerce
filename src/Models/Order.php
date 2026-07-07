<?php

namespace App\Models;

use App\Traits\HasAddreses;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\ProductsFactory> */
    use HasAddreses,
        HasFactory,
        HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payment_method',
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
            'payment_method' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::createFromTimestamp($date->getTimestamp())->diffForHumans();
        // return $date->format('Y-m-d');
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id',
        );
    }

    public function orderDetails()
    {
        return $this->hasMany(
            OrderDetail::class,
            'order_id',
            'id',
        );
    }

    public function total()
    {
        $t = 0;

        foreach ($this->orderDetails->load(['plus']) as $i => $detail) {
            foreach ($detail->plus as $j => $plu) {
                $t += $plu->price;
            }
        }
        return $t;
    }
}

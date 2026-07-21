<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    /** @use HasFactory<\Database\Factories\AddressFactory> */
    use HasFactory,
        HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'city',
        'country',
        'latitude',
        'longitude',
        'line_1',
        'line_2',
        'state',
        'zip_code',
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
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo(
            'model',
            'model_type',
            'model_id',
            'id',
        );
    }

    public function order()
    {
        return $this->morphedByMany(
            Order::class,
            'model',
            'model_has_addresses',
            'address_id',
            'model_id',
        );
    }
}

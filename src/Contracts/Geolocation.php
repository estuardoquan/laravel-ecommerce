<?php

namespace EQ\LaravelGeolocation\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int|string $id
 * @property string $city
 * @property string $country
 * @property float $latitude
 * @property float $longitude
 * @property string $line_1
 * @property string|null $line_2
 * @property string $state
 * @property string $zipcode
 *
 * @mixin \EQ\LaravelGeolocation\Models\Geolocation
 *
 * @phpstan-require-extends \EQ\LaravelGeolocation\Models\Geolocation
 */
interface Geolocation
{
    public function owner(): MorphTo;

    /**
     * Find a geolocation by its id.
     *
     */
    // public static function findById(int|string $id): self;

    /**
     * Find or Create a geolocation by its name and guard name.
     */
    // public static function findOrCreate(array $attributes): self;
}

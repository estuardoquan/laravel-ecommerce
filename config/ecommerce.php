<?php

return [
    'models' => [

        /*
         * When using the "HasGeolocations" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your geolocations. Of course, it
         * is often just the "Geolocation" model but you may use whatever you like.
         *
         * The model you want to use as the "Geolocation" model needs to implement the
         * `EQ\LaravelGeolocation\Contracts\Geolocation` contract.
         */

        'plu' => \EQ\LaravelEcommerce\Models\Plu::class,
        'product' => \EQ\LaravelEcommerce\Models\Product::class,
        'product_variant' => \EQ\LaravelEcommerce\Models\ProductVariant::class,
    ],
    'table_names' => [

        /*
         * When using the "HasGeolocations" trait from this package, we need to know which
         * table should be used to retrieve your geolocations. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */
        'plus' => 'plus',
        'products' => 'products',
        'product_variants' => 'product_variants',

        /*
         * When using the "HasGeolocations" trait from this package, we need to know which
         * table should be used to retrieve your models geolocations. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_plus' => 'model_has_plus',
    ],
    'column_names' => [

        /*
         * Change this if you want to name the related model primary key other than
         * `model_id`.
         *
         * For example, this would be nice if your primary keys are all UUIDs. In
         * that case, name this `model_uuid`.
         */

        'owner_morph_key' => 'model_id',

        /*
         * Change this if you want to name the related pivots other than defaults
         */

        'plu_pivot_key' => null, // default 'plu_id',



        /*
         * Change this if you want to name the related model primary key other than
         * `model_id`.
         *
         * For example, this would be nice if your primary keys are all UUIDs. In
         * that case, name this `model_uuid`.
         */

        'model_morph_key' => 'model_id',
    ],

    /*
     * Events will fire when a geolocation is assigned/unassigned:
     * \EQ\Geolocation\Events\GeolocationAttached
     * \EQ\Geolocation\Events\GeolocationDetached
     *
     * To enable, set to true, and then create listeners to watch these events.
     */

    'events_enabled' => false,

    /* Cache-specific settings */

    'cache' => [

        /*
         * By default all geolocations are cached for 24 hours to speed up performance.
         * When geolocations are updated the cache is flushed automatically.
         */

        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * The cache key used to store all permissions.
         */

        'key' => 'eq.ecommerce.cache',

        /*
         * You may optionally indicate a specific cache driver to use for geolocation caching
         * using any of the `store` drivers listed in the cache.php config
         * file. Using 'default' here means to use the `default` set in cache.php.
         */

        'store' => 'default',
    ],
];

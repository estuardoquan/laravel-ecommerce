<?php

namespace EQ\LaravelEcommerce;

use EQ\LaravelEcommerce\Contracts\Plu;
use EQ\LaravelEcommerce\Contracts\Product;
use EQ\LaravelEcommerce\Contracts\ProductVariant;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EcommerceRegistrar
{
    protected Repository $cache;

    protected CacheManager $cacheManager;

    protected string $pluClass;

    protected string $productClass;

    protected string $productVariantClass;

    protected Collection|array|null $products = null;

    public string $pivotGeolocation;

    public \DateInterval|int $cacheExpirationTime;

    public string $cacheKey;

    private array $cachedPlus = [];

    private array $cachedProductVariants = [];

    private array $alias = [];

    private array $except = [];

    private bool $isLoadingProducts = false;

    /**
     * GeolocationRegistrar constructor.
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->pluClass = config('ecommerce.models.plu');

        $this->productClass = config('ecommerce.models.product');

        $this->productVariantClass = config('ecommerce.models.product_variant');

        $this->cacheManager = $cacheManager;

        $this->initializeCache();
    }

    public function initializeCache(): void
    {
        $this->cacheExpirationTime = config('ecommerce.cache.expiration_time')
            ?: \DateInterval::createFromDateString('24 hours');

        $this->cacheKey = config('ecommerce.cache.key');

        $this->pivotGeolocation = config('ecommerce.column_names.geolocation_pivot_key')
            ?: 'geolocation_id';

        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): Repository
    {
        // the 'default' fallback here is from the geolocation.php config file,
        // where 'default' means to use config(cache.default)
        $cacheDriver = config('ecommerce.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return $this->cacheManager->store($cacheDriver);
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedProducts(): bool
    {
        $this->clearProductsCollection();

        return $this->cache->forget($this->cacheKey);
    }

    /**
     * Clear already-loaded geolocations collection.
     * This is only intended to be called by the GeolocationServiceProvider on boot,
     * so that long-running instances like Octane or Swoole don't keep old data in memory.
     */
    public function clearProductsCollection(): void
    {
        $this->products = null;
    }

    /**
     * Load locations from cache
     * And turns locations array into a \Illuminate\Database\Eloquent\Collection
     */
    private function loadProducts(int $retries = 0): void
    {
        if ($this->products ?? null) {
            return;
        }

        if ($this->isLoadingProducts && $retries < 10) {
            usleep(10000);

            $retries++;

            $this->loadProducts($retries);

            return;
        }

        $this->isLoadingProducts = true;

        try {
            $this->products = $this->cache->remember(
                $this->cacheKey,
                $this->cacheExpirationTime,
                fn() => $this->getSerializedProductsForCache()
            );

            $this->alias = $this->products['alias'];

            $this->hydratePlusCache();

            $this->hydrateProductVariantsCache();

            $this->products = $this->getHydratedProductCollection();

            $this->cachedPlus = $this->cachedProductVariants = $this->alias = $this->except = [];
        } finally {
            $this->isLoadingProducts = false;
        }
    }

    /**
     * Get the geolocations based on the passed params.
     */
    public function getProducts(array $params = [], bool $onlyOne = false): Collection
    {
        $this->loadProducts();

        $method = $onlyOne ? 'first' : 'filter';

        $products = $this->products->{$method}(
            static function ($product) use ($params) {
                foreach ($params as $attr => $value) {
                    if ($product->getAttribute($attr) != $value) {
                        return false;
                    }
                }

                return true;
            }
        );

        if ($onlyOne) {
            $products = new Collection($products ? [$products] : []);
        }

        return $products;
    }

    public function setPluClass($pluClass)
    {
        $this->pluClass = $pluClass;

        config()->set('ecommerce.models.plu', $pluClass);

        app()->bind(Plu::class, $pluClass);

        return $this;
    }

    public function setProductClass($productClass)
    {
        $this->productClass = $productClass;

        config()->set('ecommerce.models.product', $productClass);

        app()->bind(Product::class, $productClass);

        return $this;
    }

    public function setProductVariantClass($productVariantClass)
    {
        $this->productVariantClass = $productVariantClass;

        config()->set('ecommerce.models.product_variant', $productVariantClass);

        app()->bind(ProductVariant::class, $productVariantClass);

        return $this;
    }

    public function getPluClass(): string
    {
        return $this->pluClass;
    }

    public function getProductClass(): string
    {
        return $this->productClass;
    }
    public function getProductVariantClass(): string
    {
        return $this->productVariantClass;
    }

    public function getCacheRepository(): Repository
    {
        return $this->cache;
    }

    public function getCacheStore(): Store
    {
        return $this->cache->getStore();
    }

    protected function getProductsWithMultiple(string ...$relations): Collection
    {
        return $this->productClass::select()
            ->with($relations)
            ->get();
    }

    /**
     * Changes array keys with alias
     */
    private function aliasedArray($model): array
    {
        return collect(is_array($model) ? $model : $model->getAttributes())
            ->except($this->except)
            ->keyBy(fn($v, $k) => $this->alias[$k] ?? $k)
            ->all();
    }

    /**
     * Array for cache alias
     */
    private function aliasModelFields($newKeys = []): void
    {
        $i = 0;
        $a = ! count($this->alias) ? range('a', 'm') : range('n', 'z');

        $k = (object) $newKeys;

        foreach (array_keys($k->getAttributes()) as $v) {
            if (! isset($this->alias[$v])) {
                $this->alias[$v] = $a[$i++] ?? $v;
            }
        }

        $this->alias = array_diff_key($this->alias, array_flip($this->except));
    }

    /*
     * Make the cache smaller using an array with only required fields
     */
    private function getSerializedProductsForCache(): array
    {
        $this->except = config('ecommerce.cache.column_names_except', [
            'created_at',
            'updated_at',
            'deleted_at'
        ]);

        $products = $this->getProductsWithMultiple(
            'plus',
            'productVariants',
            'productVariants.plus'
        )->map(function ($product) {
            if (!$this->alias) {
                $this->aliasModelFields($product);
            }

            return $this->aliasedArray($product) +
                $this->getSerializedPluRelation($product) + $this->getSerializedProductVariantRelation($product);
        })->all();

        $plus = array_values($this->cachedPlus);
        $variants = array_values($this->cachedProductVariants);

        return ['alias' => array_flip($this->alias)] + compact('products', 'plus', 'variants');
    }

    private function getSerializedPluRelation(Model $model): array
    {
        if (! $model->plus->count()) {
            return [];
        }

        if (! isset($this->alias['plus'])) {
            $this->alias['plus'] = 'p';
            $this->aliasModelFields($model->plus[0]);
        }

        return [
            'p' => $model->plus->map(function ($plu) {
                if (! isset($this->cachedPlus[$plu->getKey()])) {
                    $this->cachedPlus[$plu->getKey()] = $this->aliasedArray($plu);
                }

                return $plu->getKey();
            })->all(),
        ];
    }

    private function getSerializedProductVariantRelation(Model $product)
    {
        if (! $product->productVariant->count()) {
            return [];
        }

        if (! isset($this->alias['productVariant'])) {
            $this->alias['productVariant'] = 'v';
            $this->aliasModelFields($product->productVariant[0]);
        }

        return [
            'v' => $product->productVariant->map(function ($variant) {
                $key = $variant->getKey();

                if (! isset($this->cachedProductVariants[$key])) {
                    $this->cachedProductVariants[$key] = $this->aliasedArray($variant) + $this->getSerializedPluRelation($variant);
                }

                return $key;
            })->all(),
        ];
    }

    private function getHydratedProductCollection(): Collection
    {
        $productInstance = (new ($this->getProductClass())())->newInstance([], true);

        return Collection::make(array_map(
            fn($item) => (clone $productInstance)
                ->setRawAttributes($this->aliasedArray(array_diff_key($item, ['p' => 0, 'v' => 0])), true)
                ->setRelation('plus', $this->getHydratedPluCollection($item['p'] ?? []))
                ->setRelation('productVariants', $this->getHydratedProductVariantCollection($item['v'] ?? [])),
            $this->products['products']
        ));
    }

    private function hydratePlusCache(): void
    {
        $plusInstance = (new ($this->getPluClass())())->newInstance([], true);

        array_map(function ($item) use ($plusInstance) {
            $plus = (clone $plusInstance)
                ->setRawAttributes($this->aliasedArray($item), true);
            $this->cachedPlus[$plus->getKey()] = $plus;
        }, $this->products['plus']);

        $this->products['plus'] = [];
    }

    private function hydrateProductVariantsCache(): void
    {
        $variantInstance = (new ($this->getProductVariantClass())())->newInstance([], true);

        array_map(function ($item) use ($variantInstance) {
            $variant = (clone $variantInstance)
                ->setRawAttributes($this->aliasedArray($item), true);
            $this->cachedProductVariants[$variant->getKey()] = $variant;
        }, $this->products['productVariants']);

        $this->products['productVariants'] = [];
    }

    private function getHydratedPluCollection(array $plus): Collection
    {
        return Collection::make(array_values(
            array_intersect_key($this->cachedPlus, array_flip($plus))
        ));
    }

    private function getHydratedProductVariantCollection(array $variants): Collection
    {
        return Collection::make(array_values(
            array_intersect_key($this->cachedProductVariants, array_flip($variants))
        ));
    }

    public static function isUid($value): bool
    {
        if (! is_string($value) || empty(trim($value))) {
            return false;
        }

        // check if is UUID/GUID
        $uid = preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) > 0;
        if ($uid) {
            return true;
        }

        // check if is ULID
        $ulid = strlen($value) == 26 && strspn($value, '0123456789ABCDEFGHJKMNPQRSTVWXYZabcdefghjkmnpqrstvwxyz') == 26 && $value[0] <= '7';
        if ($ulid) {
            return true;
        }

        return false;
    }
}

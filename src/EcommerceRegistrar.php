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
    /**
     * Markers used in the serialized cache payload for relation ID arrays.
     * Deliberately NOT single a-z letters so they can never collide with
     * column aliases (spatie uses 'r', which only works because its two
     * alias ranges never reach that letter — with three models we can't
     * make that guarantee, so we step outside the alphabet entirely).
     */
    private const RELATION_PLUS = '_p';

    private const RELATION_VARIANTS = '_v';

    /**
     * Disjoint alias letter ranges — one per model family.
     * Columns shared across models (id, name, slug, ...) are aliased once,
     * on first sight, and reuse that letter everywhere. Each range only
     * needs to cover the columns UNIQUE to that model. If a custom model
     * exhausts its range, the raw column name is used as-is (safe: a real
     * column name can't collide with a single letter).
     */
    private const ALIAS_RANGE_PRODUCT = ['a', 'h'];

    private const ALIAS_RANGE_PLU = ['j', 'm'];

    private const ALIAS_RANGE_VARIANT = ['n', 'u'];

    protected Repository $cache;

    protected CacheManager $cacheManager;

    protected string $pluClass;

    protected string $productClass;

    protected string $productVariantClass;

    protected Collection|array|null $products = null;

    public \DateInterval|int $cacheExpirationTime;

    public string $cacheKey;

    private array $cachedPlus = [];

    private array $cachedProductVariants = [];

    private array $alias = [];

    private array $except = [];

    private bool $isLoadingProducts = false;

    /**
     * EcommerceRegistrar constructor.
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

        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): Repository
    {
        // the 'default' fallback here is from the ecommerce.php config file,
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
     * Clear the already-loaded products collection.
     * This is only intended to be called by the EcommerceServiceProvider on boot,
     * so that long-running instances like Octane or Swoole don't keep old data in memory.
     */
    public function clearProductsCollection(): void
    {
        $this->products = null;
    }

    /**
     * Load products from the cache (building it from the DB on a miss)
     * and hydrate the payload into an Eloquent Collection.
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

            // stored flipped: [letter => column]
            $this->alias = $this->products['alias'];

            // ORDER MATTERS: plus must be hydrated before variants,
            // because each variant re-attaches its own plus relation
            // from the shared $cachedPlus pool.
            $this->hydratePlusCache();

            $this->hydrateProductVariantsCache();

            $this->products = $this->getHydratedProductCollection();
        } finally {
            $this->cachedPlus = $this->cachedProductVariants = $this->alias = $this->except = [];

            $this->isLoadingProducts = false;
        }
    }

    /**
     * Get the products based on the passed params.
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

    protected function getProductsWithRelations(): Collection
    {
        return $this->productClass::select()
            ->with('plus', 'productVariants', 'productVariants.plus')
            ->get();
    }

    /**
     * Changes array keys with alias.
     * During serialization $this->alias is [column => letter] so this shrinks keys;
     * during hydration it holds the flipped map [letter => column] so the very
     * same method expands them back.
     */
    private function aliasedArray($model): array
    {
        return collect(is_array($model) ? $model : $model->getAttributes())
            ->except($this->except)
            ->keyBy(fn($v, $k) => $this->alias[$k] ?? $k)
            ->all();
    }

    /**
     * Assign alias letters for a model's columns from the given range.
     * Columns already aliased (shared with another model) keep their letter.
     * Excluded columns (timestamps etc.) are skipped up front so they don't
     * waste letters from the range.
     */
    private function aliasModelFields(Model $model, array $span): void
    {
        $i = 0;
        $letters = range($span[0], $span[1]);

        foreach (array_keys($model->getAttributes()) as $column) {
            if (\in_array($column, $this->except, true)) {
                continue;
            }

            if (! isset($this->alias[$column])) {
                $this->alias[$column] = $letters[$i++] ?? $column;
            }
        }
    }

    /*
     * Make the cache smaller using an array with only required fields
     */
    private function getSerializedProductsForCache(): array
    {
        $this->except = config('ecommerce.cache.column_names_except', [
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $products = $this->getProductsWithRelations()
            ->map(function ($product) {
                if (! $this->alias) {
                    $this->aliasModelFields($product, self::ALIAS_RANGE_PRODUCT);
                }

                return $this->aliasedArray($product)
                    + $this->getSerializedPluRelation($product)
                    + $this->getSerializedProductVariantRelation($product);
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
            $this->alias['plus'] = self::RELATION_PLUS;
            $this->aliasModelFields($model->plus[0], self::ALIAS_RANGE_PLU);
        }

        return [
            self::RELATION_PLUS => $model->plus->map(function ($plu) {
                if (! isset($this->cachedPlus[$plu->getKey()])) {
                    $this->cachedPlus[$plu->getKey()] = $this->aliasedArray($plu);
                }

                return $plu->getKey();
            })->all(),
        ];
    }

    private function getSerializedProductVariantRelation(Model $product): array
    {
        if (! $product->productVariants->count()) {
            return [];
        }

        if (! isset($this->alias['productVariants'])) {
            $this->alias['productVariants'] = self::RELATION_VARIANTS;
            $this->aliasModelFields($product->productVariants[0], self::ALIAS_RANGE_VARIANT);
        }

        return [
            self::RELATION_VARIANTS => $product->productVariants->map(function ($variant) {
                $key = $variant->getKey();

                if (! isset($this->cachedProductVariants[$key])) {
                    $this->cachedProductVariants[$key] = $this->aliasedArray($variant)
                        + $this->getSerializedPluRelation($variant);
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
                ->setRawAttributes(
                    $this->aliasedArray(array_diff_key($item, [self::RELATION_PLUS => 0, self::RELATION_VARIANTS => 0])),
                    true
                )
                ->setRelation('plus', $this->getHydratedPluCollection($item[self::RELATION_PLUS] ?? []))
                ->setRelation('productVariants', $this->getHydratedProductVariantCollection($item[self::RELATION_VARIANTS] ?? [])),
            $this->products['products']
        ));
    }

    private function hydratePlusCache(): void
    {
        $pluInstance = (new ($this->getPluClass())())->newInstance([], true);

        array_map(function ($item) use ($pluInstance) {
            $plu = (clone $pluInstance)
                ->setRawAttributes($this->aliasedArray($item), true);

            $this->cachedPlus[$plu->getKey()] = $plu;
        }, $this->products['plus']);

        $this->products['plus'] = [];
    }

    private function hydrateProductVariantsCache(): void
    {
        $variantInstance = (new ($this->getProductVariantClass())())->newInstance([], true);

        array_map(function ($item) use ($variantInstance) {
            $variant = (clone $variantInstance)
                ->setRawAttributes(
                    $this->aliasedArray(array_diff_key($item, [self::RELATION_PLUS => 0])),
                    true
                )
                ->setRelation('plus', $this->getHydratedPluCollection($item[self::RELATION_PLUS] ?? []));

            $this->cachedProductVariants[$variant->getKey()] = $variant;
        }, $this->products['variants']);

        $this->products['variants'] = [];
    }

    /**
     * Disjoint alias letter ranges — one per model family.
     * Columns shared across models (id, name, slug, ...) are aliased once,
     * on first sight, and reuse that letter everywhere. Each range only
     * needs to cover the columns UNIQUE to that model. If a custom model
     * exhausts its range, the raw column name is used as-is (safe: a real
     * column name can't collide with a single letter).
     */
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

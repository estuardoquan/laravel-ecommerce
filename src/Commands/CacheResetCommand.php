<?php

namespace EQ\LaravelEcommerce\Commands;

use EQ\LaravelEcommerce\EcommerceRegistrar;
use Illuminate\Console\Command;

class CacheResetCommand extends Command
{
    protected $signature = 'ecommerce:cache-reset';

    protected $description = 'Reset the ecommerce products cache';

    public function handle(): int
    {
        $registrar = app(EcommerceRegistrar::class);

        $cacheExists = $registrar->getCacheRepository()->has($registrar->cacheKey);

        if ($registrar->forgetCachedProducts()) {
            $this->info('Ecommerce cache flushed.');
        } elseif ($cacheExists) {
            $this->error('Unable to flush cache.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

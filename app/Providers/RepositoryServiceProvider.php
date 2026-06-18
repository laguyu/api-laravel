<?php

namespace App\Providers;

use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Decorators\CachedProductRepository;
use App\Repositories\Eloquent\EloquentCartRepository;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Cart Repository
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);

        // Bind Order Repository
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

        // Bind Product Repository with Caching Decorator (transparently wraps Eloquent repo)
        $this->app->bind(ProductRepositoryInterface::class, function ($app) {
            return new CachedProductRepository(
                new EloquentProductRepository
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Repositories\Decorators;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CachedProductRepository implements ProductRepositoryInterface
{
    protected ProductRepositoryInterface $next;

    public function __construct(ProductRepositoryInterface $next)
    {
        $this->next = $next;
    }

    public function all(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        // Generate a unique cache key based on filters, page number, and items per page
        $cacheKey = 'products.list.'.md5(serialize($filters).'.'.$perPage.'.'.request('page', 1));

        // Cache the list for 10 minutes (600 seconds)
        return Cache::remember($cacheKey, 600, function () use ($filters, $perPage) {
            return $this->next->all($filters, $perPage);
        });
    }

    public function findById(int $id): ?Product
    {
        $cacheKey = "products.detail.{$id}";

        // Cache the single product details
        return Cache::remember($cacheKey, 600, function () use ($id) {
            return $this->next->findById($id);
        });
    }

    public function updateStock(int $id, int $quantity): bool
    {
        $updated = $this->next->updateStock($id, $quantity);

        if ($updated) {
            // Invalidate cached detail to prevent stale reads
            Cache::forget("products.detail.{$id}");
        }

        return $updated;
    }
}

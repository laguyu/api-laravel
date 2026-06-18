<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /**
     * Get all products, optionally filtered and paginated.
     */
    public function all(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Find a product by ID.
     */
    public function findById(int $id): ?Product;

    /**
     * Decrement/increment product stock.
     */
    public function updateStock(int $id, int $quantity): bool;
}

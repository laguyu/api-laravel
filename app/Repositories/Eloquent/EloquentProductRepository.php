<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function all(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['category'])
            ->withAvg('reviews', 'rating'); // Prevents N+1 queries for average review rating

        if (! empty($filters['category'])) {
            $query->whereHas('category', function ($q) use ($filters) {
                $q->where('slug', $filters['category']);
            });
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('description', 'like', '%'.$filters['search'].'%');
            });
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['in_stock']) && $filters['in_stock'] === true) {
            $query->where('stock', '>', 0);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['price', 'created_at', 'name'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Product
    {
        return Product::with(['category'])
            ->withAvg('reviews', 'rating')
            ->find($id);
    }

    public function updateStock(int $id, int $quantity): bool
    {
        $product = Product::find($id);
        if (! $product) {
            return false;
        }

        $product->stock += $quantity;

        return $product->save();
    }
}

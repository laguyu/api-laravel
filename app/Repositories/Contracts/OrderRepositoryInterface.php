<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    /**
     * Create a new order with items.
     */
    public function create(array $data): Order;

    /**
     * Get all orders for a specific user.
     */
    public function findForUser(int $userId): Collection;

    /**
     * Find an order by ID.
     */
    public function findById(int $id): ?Order;
}

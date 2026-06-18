<?php

namespace App\Repositories\Contracts;

use App\Models\CartItem;
use Illuminate\Support\Collection;

interface CartRepositoryInterface
{
    /**
     * Get all cart items for a specific user.
     */
    public function getForUser(int $userId): Collection;

    /**
     * Add or update an item in the user's cart.
     */
    public function add(int $userId, int $productId, int $quantity): CartItem;

    /**
     * Update the quantity of a specific cart item.
     */
    public function updateQuantity(int $cartItemId, int $quantity): bool;

    /**
     * Remove an item from the cart.
     */
    public function remove(int $cartItemId): bool;

    /**
     * Clear all items from a user's cart.
     */
    public function clearForUser(int $userId): void;
}

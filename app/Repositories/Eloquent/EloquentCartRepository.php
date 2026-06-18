<?php

namespace App\Repositories\Eloquent;

use App\Models\CartItem;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentCartRepository implements CartRepositoryInterface
{
    public function getForUser(int $userId): Collection
    {
        return CartItem::query()
            ->with(['product']) // Prevent N+1 queries when loading cart products
            ->where('user_id', $userId)
            ->get();
    }

    public function add(int $userId, int $productId, int $quantity): CartItem
    {
        $cartItem = CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();

            return $cartItem;
        }

        return CartItem::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    public function updateQuantity(int $cartItemId, int $quantity): bool
    {
        $cartItem = CartItem::find($cartItemId);
        if (! $cartItem) {
            return false;
        }

        $cartItem->quantity = $quantity;

        return $cartItem->save();
    }

    public function remove(int $cartItemId): bool
    {
        $cartItem = CartItem::find($cartItemId);
        if (! $cartItem) {
            return false;
        }

        return $cartItem->delete();
    }

    public function clearForUser(int $userId): void
    {
        CartItem::where('user_id', $userId)->delete();
    }
}

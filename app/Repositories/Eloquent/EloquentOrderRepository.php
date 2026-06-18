<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function create(array $data): Order
    {
        // Handled within a database transaction to ensure data integrity
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'user_id' => $data['user_id'],
                'status' => $data['status'] ?? 'pending',
                'total_amount' => $data['total_amount'],
                'billing_address' => $data['billing_address'],
                'payment_id' => $data['payment_id'] ?? null,
                'payment_gateway' => $data['payment_gateway'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return $order;
        });
    }

    public function findForUser(int $userId): Collection
    {
        return Order::query()
            ->with(['items.product']) // Eager load to avoid N+1 query loops
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findById(int $id): ?Order
    {
        return Order::query()
            ->with(['items.product'])
            ->find($id);
    }
}

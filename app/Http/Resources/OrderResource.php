<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'billing_address' => $this->billing_address,
            'payment_id' => $this->payment_id,
            'payment_gateway' => $this->payment_gateway,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                        ],
                        'quantity' => $item->quantity,
                        'price' => (float) $item->price,
                        'subtotal' => (float) ($item->price * $item->quantity),
                    ];
                });
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

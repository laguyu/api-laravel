<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'subtotal' => $this->whenLoaded('product', function () {
                return (float) ($this->product->price * $this->quantity);
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

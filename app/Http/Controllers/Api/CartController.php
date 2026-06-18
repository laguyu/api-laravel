<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Resources\CartResource;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected CartRepositoryInterface $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $cartItems = $this->cartRepository->getForUser($request->user()->id);

        return $this->successResponse('Carrito obtenido correctamente.', CartResource::collection($cartItems));
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        $cartItem = $this->cartRepository->add(
            $request->user()->id,
            $request->product_id,
            $request->quantity
        );

        return $this->successResponse('Producto agregado al carrito.', new CartResource($cartItem->load('product')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $updated = $this->cartRepository->updateQuantity($id, $request->quantity);

        if (! $updated) {
            return $this->errorResponse('No se pudo actualizar el carrito. El articulo no existe o no te pertenece.', 404);
        }

        return $this->successResponse('Cantidad del articulo actualizada.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->cartRepository->remove($id);

        if (! $deleted) {
            return $this->errorResponse('El articulo no existe o no te pertenece.', 404);
        }

        return $this->successResponse('Producto eliminado del carrito.');
    }
}

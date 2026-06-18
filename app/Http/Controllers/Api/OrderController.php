<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderRepository->findForUser($request->user()->id);

        return $this->successResponse('Pedidos obtenidos correctamente.', OrderResource::collection($orders));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderRepository->findById($id);

        if (! $order || $order->user_id !== $request->user()->id) {
            abort(404, 'Pedido no encontrado.');
        }

        return $this->successResponse('Pedido obtenido correctamente.', new OrderResource($order));
    }
}

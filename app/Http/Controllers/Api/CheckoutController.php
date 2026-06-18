<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(CheckoutRequest $request): JsonResponse
    {
        $order = $this->checkoutService->checkout(
            $request->user(),
            $request->billing_address,
            $request->payment_method
        );

        return $this->successResponse(
            'Compra realizada exitosamente.',
            new OrderResource($order->load('items.product')),
            201
        );
    }
}

<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Exceptions\OutOfStockException;
use App\Exceptions\PaymentFailedException;
use App\Gateways\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    protected CartRepositoryInterface $cartRepository;

    protected ProductRepositoryInterface $productRepository;

    protected OrderRepositoryInterface $orderRepository;

    protected PaymentGatewayInterface $paymentGateway;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        PaymentGatewayInterface $paymentGateway
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentGateway = $paymentGateway;
    }

    /**
     * Process checkout for a user.
     *
     * @throws OutOfStockException
     * @throws PaymentFailedException
     */
    public function checkout(User $user, string $billingAddress, string $paymentMethod): Order
    {
        $cartItems = $this->cartRepository->getForUser($user->id);

        if ($cartItems->isEmpty()) {
            throw new \InvalidArgumentException('No se puede realizar el checkout con un carrito vacío.');
        }

        // 1. Validate Stock Availability
        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                throw new OutOfStockException("El producto '{$item->product->name}' no tiene suficiente stock (Disponible: {$item->product->stock}, Solicitado: {$item->quantity}).");
            }
        }

        // 2. Calculate Total
        $totalAmount = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        // 3. Process Transaction with Database Transaction
        $order = DB::transaction(function () use ($user, $billingAddress, $paymentMethod, $cartItems, $totalAmount) {
            // Deduct product inventory first to avoid race conditions
            $orderItems = [];
            foreach ($cartItems as $item) {
                $this->productRepository->updateStock($item->product_id, -$item->quantity);

                $orderItems[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ];
            }

            // Charge the customer
            $paymentResult = $this->paymentGateway->charge($totalAmount, $paymentMethod);

            if (! $paymentResult->success) {
                // Throwing exception inside the transaction block automatically triggers rollback
                throw new PaymentFailedException($paymentResult->errorMessage);
            }

            // Save order and items
            $order = $this->orderRepository->create([
                'user_id' => $user->id,
                'status' => 'paid',
                'total_amount' => $totalAmount,
                'billing_address' => $billingAddress,
                'payment_id' => $paymentResult->transactionId,
                'payment_gateway' => config('services.payment.gateway', 'stripe'),
                'items' => $orderItems,
            ]);

            // Clear user cart
            $this->cartRepository->clearForUser($user->id);

            return $order;
        });

        // 4. Dispatch Event (outside DB transaction to prevent queue jobs from executing before commit)
        event(new OrderPlaced($order));

        return $order;
    }
}

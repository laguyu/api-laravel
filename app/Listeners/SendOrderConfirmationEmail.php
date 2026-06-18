<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $user = $order->user;

        // In a production environment, you would call Mail::to($user->email)->send(new OrderConfirmationMail($order));
        // For demonstration purposes, we write a descriptive log entry showing asynchronous queueing.
        Log::info("Asynchronous Queue Listener: Correo de confirmación encolado enviado para pedido #{$order->id} a {$user->email}. Total: {$order->total_amount} USD.");
    }
}

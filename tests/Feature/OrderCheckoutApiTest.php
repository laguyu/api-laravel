<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_api_user_flow(): void
    {
        Event::fake();

        // 1. Register User
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $registerResponse->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at'],
                'access_token',
                'token_type',
            ]);

        $token = $registerResponse->json('access_token');
        $headers = ['Authorization' => "Bearer {$token}"];

        // 2. Add Product
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Designer Jeans',
            'slug' => 'designer-jeans',
            'description' => 'High quality jeans',
            'price' => 89.99,
            'stock' => 10,
        ]);

        // 3. Add product to cart (Authenticated)
        $cartResponse = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], $headers);

        $cartResponse->assertStatus(201)
            ->assertJsonPath('message', 'Producto agregado al carrito.')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'quantity',
                    'product' => ['id', 'name', 'price'],
                    'subtotal',
                ],
            ]);

        // 4. View Shopping Cart
        $viewCartResponse = $this->getJson('/api/v1/cart', $headers);
        $viewCartResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'quantity' => 2,
                'subtotal' => 179.98,
            ]);

        // 5. Checkout Cart (Authenticated)
        $checkoutResponse = $this->postJson('/api/v1/orders/checkout', [
            'billing_address' => '456 Elm St, NY',
            'payment_method' => 'stripe_card',
        ], $headers);

        $checkoutResponse->assertStatus(201)
            ->assertJsonPath('message', 'Compra realizada exitosamente.')
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'status',
                    'total_amount',
                    'billing_address',
                    'payment_id',
                    'payment_gateway',
                    'items',
                ],
            ]);

        $orderId = $checkoutResponse->json('order.id');

        // Assert Order creation side-effects (stock reduced)
        $this->assertEquals(8, $product->fresh()->stock);

        // 6. View User Order History
        $ordersResponse = $this->getJson('/api/v1/orders', $headers);
        $ordersResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $orderId,
                'status' => 'paid',
                'total_amount' => 179.98,
            ]);

        // 7. View Specific Order Details
        $singleOrderResponse = $this->getJson("/api/v1/orders/{$orderId}", $headers);
        $singleOrderResponse->assertStatus(200)
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.billing_address', '456 Elm St, NY');
    }
}

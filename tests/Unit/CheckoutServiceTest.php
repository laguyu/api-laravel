<?php

namespace Tests\Unit;

use App\DTOs\PaymentResult;
use App\Events\OrderPlaced;
use App\Exceptions\OutOfStockException;
use App\Exceptions\PaymentFailedException;
use App\Gateways\Contracts\PaymentGatewayInterface;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $checkoutService;

    protected $paymentGatewayMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the payment gateway contract to control transaction outcomes
        $this->paymentGatewayMock = $this->createMock(PaymentGatewayInterface::class);
        $this->app->instance(PaymentGatewayInterface::class, $this->paymentGatewayMock);

        // Resolve CheckoutService (which automatically injects repositories and our mock gateway)
        $this->checkoutService = $this->app->make(CheckoutService::class);
    }

    public function test_successful_checkout(): void
    {
        Event::fake();

        // Arrange
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Product 1',
            'slug' => 'product-1',
            'description' => 'Test description',
            'price' => 100.00,
            'stock' => 10,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->paymentGatewayMock->expects($this->once())
            ->method('charge')
            ->with(200.00, 'stripe_card')
            ->willReturn(new PaymentResult(true, 'ch_stripe_mock123'));

        // Act
        $order = $this->checkoutService->checkout($user, '123 Main St', 'stripe_card');

        // Assert
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('paid', $order->status);
        $this->assertEquals(200.00, $order->total_amount);
        $this->assertEquals('ch_stripe_mock123', $order->payment_id);

        // Assert stock was reduced correctly
        $this->assertEquals(8, $product->fresh()->stock);

        // Assert cart was cleared
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
        ]);

        // Assert order database records exist
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,
            'total_amount' => 200.00,
        ]);

        // Assert event was dispatched
        Event::assertDispatched(OrderPlaced::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_checkout_fails_when_out_of_stock(): void
    {
        Event::fake();

        // Arrange
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Product 1',
            'slug' => 'product-1',
            'description' => 'Test description',
            'price' => 100.00,
            'stock' => 1, // Stock: 1
        ]);

        // Quantity: 2 (out of stock)
        CartItem::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->paymentGatewayMock->expects($this->never())->method('charge');

        // Act & Assert
        $this->expectException(OutOfStockException::class);
        $this->checkoutService->checkout($user, '123 Main St', 'stripe_card');

        // Assert stock remains unchanged
        $this->assertEquals(1, $product->fresh()->stock);

        // Assert cart was NOT cleared
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // Assert no order was created
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rolls_back_database_on_payment_failure(): void
    {
        Event::fake();

        // Arrange
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Product 1',
            'slug' => 'product-1',
            'description' => 'Test description',
            'price' => 100.00,
            'stock' => 10,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Mock payment decline
        $this->paymentGatewayMock->expects($this->once())
            ->method('charge')
            ->willReturn(new PaymentResult(false, null, 'Card declined'));

        // Act & Assert
        $this->expectException(PaymentFailedException::class);

        try {
            $this->checkoutService->checkout($user, '123 Main St', 'stripe_card');
        } finally {
            // Assert stock was NOT reduced due to transaction rollback
            $this->assertEquals(10, $product->fresh()->stock);

            // Assert cart was NOT cleared due to transaction rollback
            $this->assertDatabaseHas('cart_items', [
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);

            // Assert order was NOT saved due to transaction rollback
            $this->assertDatabaseCount('orders', 0);

            // Assert event was NOT dispatched
            Event::assertNotDispatched(OrderPlaced::class);
        }
    }
}

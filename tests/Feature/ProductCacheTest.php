<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_is_cached_and_invalidated(): void
    {
        // Reset cache
        Cache::flush();

        // Arrange
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cached Phone',
            'slug' => 'cached-phone',
            'description' => 'Original description',
            'price' => 500.00,
            'stock' => 5,
        ]);

        $repository = $this->app->make(ProductRepositoryInterface::class);

        // Assert that the item is NOT cached initially
        $this->assertFalse(Cache::has("products.detail.{$product->id}"));

        // Act: Fetch product to trigger caching
        $result = $repository->findById($product->id);

        $this->assertEquals($product->id, $result->id);

        // Assert: Cache key is populated
        $this->assertTrue(Cache::has("products.detail.{$product->id}"));

        // Modify database record directly (bypassing repo) to prove caching
        Product::where('id', $product->id)->update(['description' => 'Updated in database directly']);

        // Fetch again from repository
        $resultCached = $repository->findById($product->id);

        // Assert: Repository returned cached data (stale description), proving cache hits
        $this->assertEquals('Original description', $resultCached->description);

        // Act: Update stock using repo (should trigger detail cache invalidation)
        $repository->updateStock($product->id, 5);

        // Assert: Cache was cleared
        $this->assertFalse(Cache::has("products.detail.{$product->id}"));

        // Act: Fetch again from repository
        $resultFresh = $repository->findById($product->id);

        // Assert: Repository returned the fresh database values
        $this->assertEquals('Updated in database directly', $resultFresh->description);
        $this->assertEquals(10, $resultFresh->stock);
    }
}

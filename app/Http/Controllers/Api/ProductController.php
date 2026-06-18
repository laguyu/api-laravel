<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'search', 'min_price', 'max_price', 'in_stock', 'sort_by', 'sort_order']);

        if ($request->has('in_stock')) {
            $filters['in_stock'] = filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN);
        }

        $perPage = (int) $request->get('per_page', 10);
        $products = $this->productRepository->all($filters, $perPage);

        return $this->successResponse('Productos obtenidos correctamente.', ProductResource::collection($products));
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Producto no encontrado.');
        }

        return $this->successResponse('Producto obtenido correctamente.', new ProductResource($product));
    }
}

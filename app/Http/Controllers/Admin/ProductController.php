<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    /** admin.products.index — "Sản phẩm & Quyền" (5.1). */
    public function index(Request $request): View
    {
        return view('admin.products.index', $this->productService->indexData());
    }

    /** admin.products.show. */
    public function show(Request $request, int $product): View
    {
        return view('admin.products.show', $this->productService->showData($product));
    }
}

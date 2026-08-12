<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\Visibility;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** admin.products.index — "Sản phẩm & Quyền" (5.1). */
    public function index(Request $request): View
    {
        $tabs = [
            ['label' => 'Sản phẩm', 'href' => route('admin.products.index'), 'active' => true, 'count' => Product::count()],
            ['label' => 'Quyền đã cấp', 'href' => route('admin.access-rights.index'), 'active' => false, 'count' => \App\Models\AccessRight::count()],
        ];

        $products = Product::latest()->limit(50)->get()->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'type' => $p->type->value,
            'price' => number_format($p->price).'đ',
            'visibility' => $p->visibility === Visibility::Public ? 'Công khai' : 'Riêng tư',
            'tone' => $p->visibility === Visibility::Public ? 'info' : 'neutral',
        ])->all();

        return view('admin.products.index', ['tabs' => $tabs, 'products' => $products]);
    }

    /** admin.products.show. */
    public function show(Request $request, int $product): View
    {
        $productModel = Product::findOrFail($product);

        return view('admin.products.show', ['product' => $productModel]);
    }
}

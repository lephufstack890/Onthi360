<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\ProductService;
use Illuminate\Http\RedirectResponse;
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

    public function create(): View
    {
        return view('admin.products.create', $this->productService->createFormData());
    }

    private function validationRules(): array
    {
        return [
            'type' => ['required', 'string', 'in:book,topic,exam,course'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image_path' => ['nullable', 'string', 'max:500'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'topic' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'has_print_option' => ['nullable', 'boolean'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validationRules());
        $product = $this->productService->store($data);

        return redirect()->route('admin.products.show', $product->id)->with('status', 'product-created');
    }

    public function edit(int $product): View
    {
        return view('admin.products.edit', $this->productService->editFormData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate($this->validationRules());
        $this->productService->update($product, $data);

        return redirect()->route('admin.products.show', $product->id)->with('status', 'product-updated');
    }

    /** admin.products.destroy — xóa mềm, PHẢI có lý do (10.4). */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->productService->destroy($product, $data['reason']);

        return redirect()->route('admin.products.index')->with('status', 'product-deleted');
    }
}

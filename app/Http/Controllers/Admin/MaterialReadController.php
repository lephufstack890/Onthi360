<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductReadService;
use App\Services\Student\MaterialReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialReadController extends Controller
{
    public function __construct(
        private MaterialReadService $materialRead,
        private ProductReadService $productRead,
    ) {}

    public function read(Request $request, int $material): View
    {
        $user = $request->user();
        $materialModel = $this->materialRead->findWithProductOrFail($material);

        abort_unless($this->materialRead->decisionFor($user, $materialModel)->allowed, 403);
        abort_if(blank($materialModel->pdf_path), 404);

        return view('student.materials.read', $this->materialRead->buildReadData($user, $materialModel, 'admin'));
    }

    public function pdfFile(Request $request, int $material): StreamedResponse
    {
        $materialModel = $this->materialRead->findWithProductOrFail($material);
        abort_unless($this->materialRead->decisionFor($request->user(), $materialModel)->allowed, 403);

        return $this->materialRead->streamPdf($materialModel);
    }

    public function product(Request $request, int $product): View
    {
        $user = $request->user();
        $productModel = $this->productRead->findOrFail($product);
        abort_unless($this->productRead->decisionFor($user, $productModel)->allowed, 403);

        return view('materials.product-read', $this->productRead->buildReadData($user, $productModel, 'admin'));
    }

    public function productFile(Request $request, int $product, int $material): StreamedResponse
    {
        $productModel = $this->productRead->findOrFail($product);
        abort_unless($this->productRead->decisionFor($request->user(), $productModel)->allowed, 403);

        return $this->productRead->streamPdf($this->productRead->resolvePartOrFail($productModel, $material));
    }

    public function productAsset(Request $request, int $product, int $material, string $kind): StreamedResponse
    {
        $productModel = $this->productRead->findOrFail($product);
        abort_unless($this->productRead->decisionFor($request->user(), $productModel)->allowed, 403);

        return $this->productRead->streamAsset($this->productRead->resolveAssetOrFail($productModel, $material, $kind), $kind);
    }
}

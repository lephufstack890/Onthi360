<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\ProductService;
use App\Services\PdfAssessmentEditingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request): View
    {
        return view('admin.products.index', $this->productService->indexData());
    }

    public function show(Request $request, int $product): View
    {
        return view('admin.products.show', $this->productService->showData($product));
    }

    public function create(): View
    {
        return view('admin.products.create', $this->productService->createFormData());
    }

    private const MAX_EXERCISE_ZIP_KB = 204800; // 200MB — gói bài tập có thể gồm nhiều tệp con

    private const MAX_MEDIA_KB = 51200; // 50MB — ảnh động/audio ngắn

    private function validationRules(): array
    {
        return [
            'type' => ['required', 'string', 'in:book,topic,exam,course'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'topic' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'price_teaching' => ['required', 'integer', 'min:0'],
            'has_print_option' => ['nullable', 'boolean'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'visibility' => ['required', 'string', 'in:public,private'],
            // SỬA 27/8 ("4 file đính kèm sản phẩm", đủ 4 ô sau khi bỏ khối "Học liệu thuộc sản
            // phẩm"): mỗi ô đúng 1 file, để trống = giữ nguyên file cũ (giống cover_image, xem
            // applyResourceUploads()).
            'content_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'guide_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'exercise_zip' => ['nullable', 'file', 'mimes:zip', 'max:'.self::MAX_EXERCISE_ZIP_KB],
            'media' => ['nullable', 'file', 'mimes:gif,webp,png,jpg,jpeg,mp4,mp3,wav,ogg', 'max:'.self::MAX_MEDIA_KB],
        ];
    }

    /**
     * SỬA 27/8 ("4 file đính kèm sản phẩm") — xử lý CHUNG cho cả 4 ô file (content_pdf,
     * guide_pdf, exercise_zip, media): có file mới thì xoá file cũ (nếu $existing có) rồi lưu
     * file mới vào disk 'local' (riêng tư — khác cover_image ở disk 'public' vì 4 tài nguyên
     * này PHẢI qua kiểm tra quyền mới tải được, xem AccessGateService::canAccessProduct());
     * không có file mới thì bỏ hẳn field khỏi $data để giữ nguyên giá trị cũ trong DB
     * (ProductService chỉ ghi đè khi key có mặt, giống cover_image_path).
     */
    private function applyResourceUploads(Request $request, array &$data, ?Product $existing): void
    {
        $fields = [
            'content_pdf' => ['content_pdf_path', 'content_pdf_original_name', 'products/content'],
            'guide_pdf' => ['guide_pdf_path', 'guide_pdf_original_name', 'products/guides'],
            'exercise_zip' => ['exercise_zip_path', 'exercise_zip_original_name', 'products/exercises'],
            'media' => ['media_path', 'media_original_name', 'products/media'],
        ];

        foreach ($fields as $field => [$pathKey, $nameKey, $folder]) {
            if ($request->hasFile($field)) {
                if ($existing?->{$pathKey}) {
                    Storage::disk('local')->delete($existing->{$pathKey});
                }
                $file = $request->file($field);
                $data[$pathKey] = $file->store($folder, 'local');
                $data[$nameKey] = $file->getClientOriginalName();
            }
            unset($data[$field]);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validationRules());

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('products/covers', 'public');
        }
        unset($data['cover_image']);

        $this->applyResourceUploads($request, $data, null);

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

        if ($request->hasFile('cover_image')) {
            if ($product->cover_image_path) {
                Storage::disk('public')->delete($product->cover_image_path);
            }
            $data['cover_image_path'] = $request->file('cover_image')->store('products/covers', 'public');
        }
        unset($data['cover_image']);

        $this->applyResourceUploads($request, $data, $product);

        $this->productService->update($product, $data);

        return redirect()->route('admin.products.show', $product->id)->with('status', 'product-updated');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->productService->destroy($product, $data['reason']);

        return redirect()->route('admin.products.index')->with('status', 'product-deleted');
    }
}

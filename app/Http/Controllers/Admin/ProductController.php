<?php

namespace App\Http\Controllers\Admin;

use App\Support\ProductCover;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\ProductService;
use App\Services\PdfAssessmentEditingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    private const MAX_MEDIA_KB = 51200; // 50MB — ảnh động/audio ngắn

    /**
     * SỬA 9/10 — bỏ loại "Khóa học" khỏi form tài liệu (khách yêu cầu). Riêng tài liệu CŨ đang là
     * loại khóa học vẫn được sửa/lưu (giữ nguyên loại) để không vỡ dữ liệu có sẵn → $keepType.
     */
    private function validationRules(?string $keepType = null): array
    {
        $types = ['book', 'topic', 'exam'];
        if ($keepType === 'course') {
            $types[] = 'course';
        }

        return [
            'type' => ['required', 'string', 'in:'.implode(',', $types)],
            // Ảnh bìa chọn từ catalog (id trong App\Support\ProductCover), kiểm đúng loại ở applyCatalogCover().
            'cover_catalog' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'topic' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'price_teaching' => ['required', 'integer', 'min:0'],
            'has_print_option' => ['nullable', 'boolean'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            // SỬA 9/10 — trường hiển thị trang Tài liệu (bản mẫu mới).
            'difficulty_level' => ['nullable', 'integer', 'between:1,5'],
            'author_name' => ['nullable', 'string', 'max:150'],
            'rating_score' => ['nullable', 'numeric', 'between:0,5'],
            'rating_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'visibility' => ['required', 'string', 'in:public,private'],
            // SỬA 27/8 ("4 file đính kèm sản phẩm", đủ 4 ô sau khi bỏ khối "Học liệu thuộc sản
            // phẩm"): mỗi ô đúng 1 file, để trống = giữ nguyên file cũ (giống cover_image, xem
            // applyResourceUploads()).
            // SỬA 31/8 ("ZIP bài tập" — nhập bằng ZIP, thêm được nhiều bài, chấm kiểu thi
            // online): đã bỏ ô "exercise_zip" (1 file duy nhất) khỏi form tạo/sửa sản phẩm ở
            // đây — thay bằng mục "Bài tập đính kèm" riêng ngay tại trang chi tiết sản phẩm
            // (xem admin/products/show.blade.php + Admin\ProductExerciseController). Cột
            // exercise_zip_path/exercise_zip_original_name và route tải cũ (access.resource,
            // kind=exercise) VẪN giữ nguyên, không xoá — sản phẩm nào đã có file ZIP cũ trước
            // đây vẫn xem/tải được bình thường, chỉ là không thể upload MỚI qua form nữa.
            'content_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'guide_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'media' => ['nullable', 'file', 'mimes:gif,webp,png,jpg,jpeg,mp4,mp3,wav,ogg', 'max:'.self::MAX_MEDIA_KB],
        ];
    }

    /**
     * SỬA 27/8 ("4 file đính kèm sản phẩm") — xử lý CHUNG cho các ô file còn lại (content_pdf,
     * guide_pdf, media — KHÔNG còn exercise_zip, xem ghi chú SỬA 31/8 ở validationRules()): có
     * file mới thì xoá file cũ (nếu $existing có) rồi lưu file mới vào disk 'local' (riêng tư —
     * khác cover_image ở disk 'public' vì các tài nguyên này PHẢI qua kiểm tra quyền mới tải
     * được, xem AccessGateService::canAccessProduct()); không có file mới thì bỏ hẳn field khỏi
     * $data để giữ nguyên giá trị cũ trong DB (ProductService chỉ ghi đè khi key có mặt, giống
     * cover_image_path).
     */
    private function applyResourceUploads(Request $request, array &$data, ?Product $existing): void
    {
        $fields = [
            'content_pdf' => ['content_pdf_path', 'content_pdf_original_name', 'products/content'],
            'guide_pdf' => ['guide_pdf_path', 'guide_pdf_original_name', 'products/guides'],
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

    /**
     * SỬA 9/10 — ảnh bìa chọn từ catalog thay cho ô tải ảnh lên.
     *  - Có chọn ảnh trong catalog → ghi "catalog:<id>" (phải đúng loại tài liệu, sai loại → báo lỗi).
     *  - Không chọn: tạo mới → dùng ảnh đầu tiên của loại; sửa → GIỮ NGUYÊN ảnh cũ (kể cả ảnh tải lên
     *    từ trước, không ghi đè thành null).
     *  - Đổi sang ảnh catalog thì dọn ảnh tải lên cũ trên đĩa public (không đụng tới ảnh catalog).
     */
    private function applyCatalogCover(array &$data, ?Product $product): void
    {
        $id = $data['cover_catalog'] ?? null;
        unset($data['cover_catalog']);

        if ($id === null || $id === '') {
            if ($product === null) {
                $default = ProductCover::defaultIdFor($data['type']);
                if ($default !== null) {
                    $data['cover_image_path'] = ProductCover::marker($default);
                }
            } elseif ($product->type->value !== $data['type']
                && ProductCover::isCatalog($product->cover_image_path)
                && ! ProductCover::belongsToType(ProductCover::idOf($product->cover_image_path), $data['type'])) {
                // Đổi loại mà ảnh catalog cũ không thuộc loại mới → tự chuyển sang ảnh đầu của loại mới.
                $default = ProductCover::defaultIdFor($data['type']);
                if ($default !== null) {
                    $data['cover_image_path'] = ProductCover::marker($default);
                }
            }

            return;
        }

        if (! ProductCover::belongsToType($id, $data['type'])) {
            throw ValidationException::withMessages(['cover_catalog' => 'Ảnh bìa không thuộc loại tài liệu đã chọn.']);
        }

        if ($product !== null && $product->cover_image_path && ! ProductCover::isCatalog($product->cover_image_path)) {
            Storage::disk('public')->delete($product->cover_image_path);
        }
        $data['cover_image_path'] = ProductCover::marker($id);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validationRules());

        $this->applyCatalogCover($data, null);

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
        $data = $request->validate($this->validationRules($product->type->value));

        $this->applyCatalogCover($data, $product);

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

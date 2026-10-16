<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Question;
use App\Services\Admin\ContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — CHỈ Admin quản lý (thêm/sửa/xoá) bài tập đính
 * kèm sản phẩm; đặt cùng nhóm middleware role:admin,super_admin với ProductController (xem
 * routes/web.php) — KHÔNG có route tương đương cho giáo viên. Toàn bộ logic thật nằm ở
 * App\Services\Admin\ContentService::productExercise*() — controller chỉ validate input +
 * gọi service + redirect, giống quy ước của ProductController/ContentController.
 */
class ProductExerciseController extends Controller
{
    public function __construct(private ContentService $contentService) {}

    /**
     * Chặn 1 admin sửa/xoá "nhầm" bài tập của sản phẩm KHÁC qua việc tự sửa URL — route
     * {product}/{exercise} không tự ràng buộc quan hệ, phải kiểm tra tay ở đây (cùng nguyên
     * tắc "2 request độc lập" như AccessService::downloadResource()).
     */
    private function assertBelongsToProduct(Product $product, Question $exercise): void
    {
        abort_unless($exercise->product_id === $product->id, 404);
    }

    /** admin.products.exercises.store — chọn ZIP xong, tạo bản nháp ngay rồi chuyển thẳng sang
     *  màn Sửa để admin xem lại/hoàn tất (xem ContentService::productExerciseStoreFromZipPackage()). */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'zip_package' => ['required', 'file', 'mimes:zip', 'max:'.ContentService::maxQuestionZipKb()],
            // SỬA 14/10 (khách: "cho chọn chương để biết bài tập thuộc chương nào") — kiểm đúng sản phẩm ở resolveChapterId().
            'material_id' => ['nullable', 'integer'],
        ], [], ['zip_package' => 'Gói ZIP bài tập']);

        $exercise = $this->contentService->productExerciseStoreFromZipPackage(
            $product,
            $request->user(),
            $request->file('zip_package'),
            $request->filled('material_id') ? (int) $request->input('material_id') : null,
        );

        return redirect()
            ->route('admin.products.exercises.edit', [$product->id, $exercise->id])
            ->with('status', 'exercise-parsed');
    }

    public function edit(Product $product, Question $exercise): View
    {
        $this->assertBelongsToProduct($product, $exercise);

        return view('admin.products.exercises.edit', $this->contentService->productExerciseEditFormData($product, $exercise));
    }

    /** admin.products.exercises.update — bấm "Lưu bài tập": chỉ Tiêu đề/Điểm/Tag/Chương-phần-đề
     *  sửa được, xem lý do ở ContentService::productExerciseSave(). Lưu xong -> Published, quay
     *  về trang sản phẩm, nút "Thêm ZIP" lại bấm được ngay (không còn nháp nào treo). */
    public function update(Request $request, Product $product, Question $exercise): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $exercise);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            // SỬA 14/10 (khách: "thiếu mã bài, cập nhật được mã bài") — trùng mã kiểm ở ContentService::productExerciseSave().
            'code' => ['nullable', 'string', 'max:40'],
            'points' => ['nullable', 'integer', 'min:0'],
            // SỬA 4/9 — không ràng buộc 'exists:materials,id' ở đây vì còn phải kiểm tra ĐÚNG
            // sản phẩm nữa (2 điều kiện gộp), xem ContentService::resolveChapterId() — giá trị
            // sai/không thuộc sản phẩm này sẽ tự bị bỏ qua (lưu null) chứ không lỗi 422.
            'material_id' => ['nullable', 'integer'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer'],
            'new_tags' => ['nullable', 'string', 'max:500'],
            // SỬA 14/10 — chỉ câu Lập trình dùng: bộ test, giới hạn chạy, đọc/ghi tệp, 3 tệp đính kèm (cùng luật Kho câu hỏi).
            'test_cases_json' => ['nullable', 'string', 'max:8000000'],
            'time_limit_ms' => ['nullable', 'integer', 'min:1', 'max:60000'],
            'memory_limit_mb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'io_mode' => ['nullable', 'in:std,file'],
            'file_io_input' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'file_io_output' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'statement_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'solution_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'reference_file' => ['nullable', 'file', 'max:2048', 'extensions:cpp,cc,c,py,pas,java,js,ts,txt,md'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['string', 'in:statement,solution,reference'],
            // SỬA 14/10 (khách: "trắc nghiệm, điền từ vẫn cho sửa thoải mái, lỡ nhập lộn") — dạng nhập tay: đổi dạng
            // (Trắc nghiệm / Đúng-Sai / Điền từ), sửa câu hỏi, đáp án, đáp án đúng.
            'kind' => ['nullable', 'string', 'in:mcq,true_false,fill_blank'],
            'body' => ['nullable', 'string', 'max:100000'],
            'options' => ['nullable', 'array', 'max:8'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'correct_option' => ['nullable', 'integer', 'min:0', 'max:7'],
            'tf_answer' => ['nullable', 'string', 'in:true,false'],
            'accepted_answers' => ['nullable', 'string', 'max:2000'],
            'case_sensitive' => ['nullable', 'boolean'],
        ], [], [
            'code' => 'Mã bài', 'statement_file' => 'Đề bài (PDF)', 'solution_file' => 'Lời giải (PDF)', 'reference_file' => 'Code mẫu',
            'time_limit_ms' => 'Giới hạn thời gian', 'memory_limit_mb' => 'Giới hạn bộ nhớ',
            'body' => 'Câu hỏi', 'options.*' => 'Đáp án', 'correct_option' => 'Đáp án đúng', 'accepted_answers' => 'Đáp án được chấp nhận',
        ]);

        $codingKeys = ['test_cases_json', 'time_limit_ms', 'memory_limit_mb', 'io_mode', 'file_io_input', 'file_io_output',
            'statement_file', 'solution_file', 'reference_file', 'remove_attachments'];
        $manualKeys = ['kind', 'body', 'options', 'correct_option', 'tf_answer', 'accepted_answers', 'case_sensitive'];

        if ($exercise->type === \App\Enums\QuestionType::Coding) {
            $data = array_diff_key($data, array_flip($manualKeys));
        } else {
            // Dạng khác không có các ô của câu Lập trình — bỏ hẳn để chắc chắn không đụng cấu hình chấm của chúng.
            $data = array_diff_key($data, array_flip($codingKeys));
            if (! in_array($exercise->type->value, ['mcq', 'fill_blank'], true)) {
                // Câu Nhiều phần (chỉ nhập từ ZIP) vẫn chỉ đọc phần đáp án.
                $data = array_diff_key($data, array_flip($manualKeys));
            }
        }

        try {
            $data = ContentService::testCasesFromJsonInput($data);
            $this->contentService->productExerciseSave($exercise, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.products.show', $product->id)->with('status', 'exercise-saved');
    }

    /**
     * admin.products.exercises.createManual (SỬA 4/9, khách yêu cầu "vừa thêm được từ ZIP và
     * thêm thủ công nữa") — form nhập tay, xem ContentService::productExerciseManualCreateFormData().
     */
    public function createManual(Product $product): View
    {
        return view('admin.products.exercises.create', $this->contentService->productExerciseManualCreateFormData($product));
    }

    /** admin.products.exercises.storeManual — lưu thẳng Published, xem ContentService::productExerciseStoreManual(). */
    public function storeManual(Request $request, Product $product): RedirectResponse
    {
        /*
         * SỬA 14/10 (khách: "bỏ nút Thêm thủ công; chọn dạng: Trắc nghiệm 4 đáp án, Trắc nghiệm đúng sai, Lập trình, Điền
         * từ — Lập trình thì nhập ZIP, các dạng còn lại nhập tay") — form nhập tay giờ nằm NGAY trên trang chi tiết tài
         * liệu và chỉ còn 3 dạng: mcq (4 đáp án, chọn 1) · true_false (1 nhận định Đúng/Sai) · fill_blank (điền từ).
         * Lập trình chỉ đi đường ZIP (store()). Mã bài để trống thì tự sinh.
         */
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'type' => ['required', 'string', 'in:mcq,true_false,fill_blank'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'tf_answer' => ['nullable', 'string', 'in:true,false'],
            'points' => ['nullable', 'integer', 'min:0'],
            'material_id' => ['nullable', 'integer'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'correct_option' => ['nullable', 'integer', 'min:0', 'max:3'],
            'accepted_answers' => ['nullable', 'string', 'max:2000'],
            'case_sensitive' => ['nullable', 'boolean'],
            'test_cases_raw' => ['nullable', 'string', 'max:10000'],
            'time_limit_ms' => ['nullable', 'integer', 'min:1'],
            'memory_limit_mb' => ['nullable', 'integer', 'min:1'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ], [], [
            'title' => 'Tên bài tập', 'body' => 'Đề bài', 'options.*' => 'Đáp án', 'correct_option' => 'Đáp án đúng',
            'tf_answer' => 'Đáp án Đúng/Sai', 'accepted_answers' => 'Đáp án được chấp nhận', 'points' => 'Điểm',
        ]);

        try {
            $exercise = $this->contentService->productExerciseStoreManual($product, $request->user(), $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.products.show', $product->id)->with('status', 'exercise-saved');
    }

    public function destroy(Product $product, Question $exercise): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $exercise);

        $this->contentService->productExerciseDestroy($exercise);

        return redirect()->route('admin.products.show', $product->id)->with('status', 'exercise-deleted');
    }

    /** admin.products.exercises.attachment — đề bài/lời giải/code mẫu đọc từ gói ZIP, xem
     *  ContentService::questionAttachmentInfo() (dùng lại nguyên vẹn với Kho câu hỏi). */
    public function attachmentDownload(Product $product, Question $exercise, string $kind): StreamedResponse
    {
        $this->assertBelongsToProduct($product, $exercise);

        $info = $this->contentService->questionAttachmentInfo($exercise, $kind);

        return Storage::disk('local')->download($info['path'], $info['filename']);
    }

    /**
     * admin.products.exercises.asset (31/8 (2), "mở rộng ZIP bài tập") — audio/ảnh... đính kèm
     * câu hỏi (xem Question::findAsset()) — KHÁC attachmentDownload() ở trên (3 tên CỐ ĐỊNH,
     * luôn tải xuống) ở 2 điểm: (1) định danh bằng asset id (không giới hạn số lượng/loại
     * trước), (2) dùng response() (hiện INLINE, không ép tải xuống) để admin xem/nghe thử ngay
     * trong màn Sửa bài tập (audio player/ảnh), không phải để tải về máy.
     */
    public function assetDownload(Product $product, Question $exercise, string $asset)
    {
        $this->assertBelongsToProduct($product, $exercise);

        $info = $exercise->findAsset($asset);
        abort_if($info === null, 404);

        return Storage::disk('local')->response($info['path'], $info['filename']);
    }
}

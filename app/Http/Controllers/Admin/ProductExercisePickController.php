<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Question;
use App\Services\Admin\ProductExercisePickService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 27/10 — khối "Bài tập đính kèm", cách thêm thứ 3: "Lấy từ bài có sẵn" (bài tập của tài liệu khác). Trả JSON cho
 * partials/exercise-pick-adder. Logic ở App\Services\Admin\ProductExercisePickService. Cùng nhóm route admin với
 * ProductExerciseController (chỉ Admin / Super Admin).
 */
class ProductExercisePickController extends Controller
{
    public function __construct(private ProductExercisePickService $pick) {}

    /** Danh sách tài liệu / chương / bài để chọn (đã bỏ bài tài liệu này có rồi). */
    public function sources(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'loai' => ['nullable', 'string', 'max:20'],
            'tai_lieu' => ['nullable', 'integer'],
            'chuong' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($this->pick->sources($product, $request->query()));
    }

    /** Thêm các bài đã chọn vào tài liệu này theo kiểu DÙNG KÉ (SỬA 27/10 lần 2 — không sao chép). */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'material_id' => ['nullable', 'integer'],
        ], [], ['ids' => 'Bài tập']);

        @set_time_limit(300);

        try {
            return response()->json(['ok' => true] + $this->pick->link(
                $product,
                $request->user(),
                (array) $request->input('ids', []),
                $request->filled('material_id') ? (int) $request->input('material_id') : null,
            ));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => (string) collect($e->errors())->flatten()->first()], 422);
        }
    }

    /** Gỡ bài dùng ké khỏi tài liệu này — bài gốc (ở tài liệu khác) và tệp của nó giữ nguyên. */
    public function unlink(Product $product, Question $exercise): RedirectResponse
    {
        $this->pick->unlink($product, $exercise);

        return redirect()->route('admin.products.show', $product->id)
            ->with('status', 'exercises-deleted-many')
            ->with('statusMessage', 'Đã gỡ bài '.$exercise->code.' khỏi tài liệu này (bài gốc vẫn giữ nguyên).');
    }
}

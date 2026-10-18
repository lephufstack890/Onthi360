<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Product;
use App\Services\ExamBundle\ExamBundleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SỬA 18/10 — thao tác BỘ ĐỀ của admin (khối "Đề trong bộ đề" ở trang chi tiết tài liệu loại Bộ đề, và ô "Bộ đề" ở trang
 * chi tiết đề luyện tập). Controller mỏng: kiểm tra đầu vào rồi gọi ExamBundleService — logic nằm hết ở service.
 * Routes: admin.products.bundle.* và admin.content.assessments.bundle.* (routes/web.php, nhóm admin).
 */
class ExamBundleController extends Controller
{
    public function __construct(private readonly ExamBundleService $bundles) {}

    public function saveFilters(Request $request, Product $product): RedirectResponse
    {
        $this->ensureBundle($product);
        $data = $request->validate([
            'categories' => ['nullable', 'array'], 'categories.*' => ['string', 'max:30'],
            'levels' => ['nullable', 'array'], 'levels.*' => ['string', 'max:10'],
            'provinces' => ['nullable', 'array'], 'provinces.*' => ['string', 'max:20'],
            'year_from' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1990', 'max:2100'],
        ]);

        $filters = $this->bundles->saveFilters($product, $data);

        return $this->back($product, 'Đã lưu bộ lọc: '.$filters->summary().' — đang có '.$this->bundles->items($product->fresh())->count().' đề trong bộ.');
    }

    public function lock(Request $request, Product $product): RedirectResponse
    {
        $this->ensureBundle($product);
        $locked = $request->boolean('locked');
        $this->bundles->setLocked($product, $locked);

        return $this->back($product, $locked
            ? 'Đã khoá danh sách — bộ đề giữ nguyên các đề hiện tại, không tự thêm đề mới.'
            : 'Đã mở khoá — đề mới khớp bộ lọc sẽ tự vào bộ đề.');
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        $this->ensureBundle($product);
        $data = $request->validate(['assessment_ids' => ['required', 'array', 'max:200'], 'assessment_ids.*' => ['integer']], [], ['assessment_ids' => 'Đề cần thêm']);
        $added = $this->bundles->addManual($product, $data['assessment_ids']);

        return $this->back($product, 'Đã thêm '.$added.' đề vào bộ đề.');
    }

    public function remove(Product $product, int $assessment): RedirectResponse
    {
        $this->ensureBundle($product);
        $this->bundles->remove($product, $assessment);

        return $this->back($product, 'Đã bỏ đề khỏi bộ đề (đề vẫn còn nguyên ở Luyện tập / Kho đề).');
    }

    public function restore(Product $product, int $assessment): RedirectResponse
    {
        $this->ensureBundle($product);
        $this->bundles->restore($product, $assessment);

        return $this->back($product, 'Đã đưa đề trở lại bộ đề.');
    }

    public function move(Request $request, Product $product, int $assessment): RedirectResponse
    {
        $this->ensureBundle($product);
        $this->bundles->move($product, $assessment, $request->input('direction') === 'up' ? 'up' : 'down');

        return $this->back($product, null);
    }

    public function resetOrder(Product $product): RedirectResponse
    {
        $this->ensureBundle($product);
        $this->bundles->resetOrder($product);

        return $this->back($product, 'Đã về thứ tự mặc định (năm học mới trước, tỉnh A–Z).');
    }

    /** Ô "Chỉ trong bộ đề" ở trang chi tiết đề luyện tập. */
    public function assessmentVisibility(Request $request, Assessment $assessment): RedirectResponse
    {
        abort_unless(ExamBundleService::ready(), 404);
        $only = $request->boolean('bundle_only');
        $this->bundles->setBundleOnly($assessment, $only);

        return redirect()->to(url()->previous().'#exam-bundle-box')->with('status', 'bundle-saved')->with('statusMessage', $only
            ? 'Đề đã chuyển sang "Chỉ trong bộ đề" — không hiện ở trang Luyện tập, chỉ người mua bộ đề làm được.'
            : 'Đề đã công khai lại ở trang Luyện tập (làm miễn phí).');
    }

    /** Nút "Thêm vào bộ đề…" ở trang chi tiết đề luyện tập. */
    public function assessmentAttach(Request $request, Assessment $assessment): RedirectResponse
    {
        abort_unless(ExamBundleService::ready(), 404);
        $data = $request->validate(['product_id' => ['required', 'integer']], [], ['product_id' => 'Bộ đề']);
        $product = Product::query()->findOrFail($data['product_id']);
        $this->ensureBundle($product);
        $added = $this->bundles->addManual($product, [$assessment->id]);

        return redirect()->to(url()->previous().'#exam-bundle-box')->with('status', 'bundle-saved')->with('statusMessage', $added
            ? 'Đã thêm đề vào bộ đề "'.$product->title.'".'
            : 'Chỉ thêm được đề luyện tập ĐÃ PHÁT HÀNH vào bộ đề.');
    }

    private function ensureBundle(Product $product): void
    {
        abort_unless(ExamBundleService::ready() && $this->bundles->isBundle($product), 404);
    }

    private function back(Product $product, ?string $message): RedirectResponse
    {
        $redirect = redirect()->to(route('admin.products.show', $product->id).'#exam-bundle');

        return $message === null ? $redirect : $redirect->with('status', 'bundle-saved')->with('statusMessage', $message);
    }
}

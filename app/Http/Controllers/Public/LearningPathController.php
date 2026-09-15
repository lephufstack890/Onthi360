<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\LearningPathService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B3/B4 — Lộ trình học ở trang công khai.
 *
 * Controller mỏng: mọi việc chọn lọc nằm ở App\Services\Public\LearningPathService.
 *
 * SỬA 15/9 — 2 trang này đang TẮT (LearningPathService::PUBLIC_ENABLED = false), vào sẽ ra 404.
 *
 * Cố ý chặn Ở ĐÂY chứ không gỡ 2 dòng route trong routes/web.php: tên route
 * 'learningPaths.index'/'learningPaths.show' vẫn còn được gọi rải rác trong các view (chân
 * trang, menu, sitemap, trang chi tiết khoá học). Gỡ route đi thì mọi lời gọi route() đó ném
 * RouteNotFoundException làm vỡ luôn cả những trang không liên quan. Giữ route + chặn ở
 * controller là cách tắt an toàn nhất, và bật lại cũng chỉ đúng một dòng hằng.
 */
class LearningPathController extends Controller
{
    public function __construct(private readonly LearningPathService $learningPaths) {}

    public function index(Request $request): View
    {
        abort_unless(LearningPathService::PUBLIC_ENABLED, 404);

        // Ô lọc gửi lên chuỗi rỗng khi chọn "Tất cả" — quy về null cho service khỏi phải đoán.
        $grade = $request->query('khoi');
        $grade = ($grade === null || $grade === '') ? null : (int) $grade;

        $language = $request->query('ngon-ngu');
        $language = ($language === null || $language === '') ? null : (string) $language;

        return view('public.learning-paths.index', $this->learningPaths->indexData($grade, $language));
    }

    public function show(Request $request, string $slug): View
    {
        abort_unless(LearningPathService::PUBLIC_ENABLED, 404);

        return view('public.learning-paths.show', $this->learningPaths->showData($slug, $request->user()));
    }
}

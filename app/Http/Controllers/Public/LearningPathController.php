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
 */
class LearningPathController extends Controller
{
    public function __construct(private readonly LearningPathService $learningPaths) {}

    public function index(Request $request): View
    {
        // Ô lọc gửi lên chuỗi rỗng khi chọn "Tất cả" — quy về null cho service khỏi phải đoán.
        $grade = $request->query('khoi');
        $grade = ($grade === null || $grade === '') ? null : (int) $grade;

        $language = $request->query('ngon-ngu');
        $language = ($language === null || $language === '') ? null : (string) $language;

        return view('public.learning-paths.index', $this->learningPaths->indexData($grade, $language));
    }

    public function show(Request $request, string $slug): View
    {
        return view('public.learning-paths.show', $this->learningPaths->showData($slug, $request->user()));
    }
}

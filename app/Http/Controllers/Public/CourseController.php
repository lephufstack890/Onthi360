<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\CourseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    public function index(Request $request): View
    {
        /*
         * B7 — ?lo-trinh=<id> chọn sẵn bộ lọc theo lộ trình.
         *
         * Cần có tham số trên đường dẫn chứ không chỉ bấm tại chỗ: trang lộ trình kết thúc
         * bằng nút "Xem lớp của lộ trình này", và người ta còn gửi link đó cho nhau qua Zalo.
         * Link phải mở ra đúng danh sách đã lọc sẵn, không bắt người nhận tự đi bấm lại.
         */
        $learningPath = $request->query('lo-trinh');
        $learningPath = ($learningPath === null || $learningPath === '') ? null : (int) $learningPath;

        return view('public.courses.index', $this->courseService->indexData(
            $request->query('subject'),
            $request->user(),
            $learningPath,
        ));
    }

    public function show(Request $request, int $course): View
    {
        return view('public.courses.show', $this->courseService->showData($course, $request->user()));
    }
}

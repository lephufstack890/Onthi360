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
         * SỬA 16/9 (khách: "trang lớp học ngoài public hiển thị dữ liệu lớp học mới đúng") —
         * đổi sang classIndexData(): mỗi thẻ là một LỚP HỌC đang mở, không còn gộp theo khoá.
         * Lọc theo khoá học và khối lớp chạy ngay tại trình duyệt (xem partials/courses-script)
         * nên không cần tham số trên đường dẫn.
         *
         * indexData() cũ KHÔNG xoá — showData() và trang chủ vẫn dùng chung các hàm phụ trong
         * cùng service, và cần quay lại kiểu gộp theo khoá thì đổi đúng dòng này.
         */
        // ?khoa=<id> — chọn sẵn bộ lọc theo khoá (nút "Xem lộ trình" ở trang chủ dẫn tới đây).
        $courseId = $request->query('khoa');
        $courseId = ($courseId === null || $courseId === '') ? null : (int) $courseId;

        return view('public.courses.index', $this->courseService->classIndexData($request->user(), $courseId));
    }

    /**
     * SỬA 18/9 (khách yêu cầu) — nội dung POPUP "Chi tiết lớp" ở trang Lớp học công khai.
     *
     * Trả về MẢNH GIAO DIỆN (không phải cả trang) để trang danh sách nạp đúng lớp vừa bấm, thay
     * vì nhồi sẵn dữ liệu của cả 120 lớp vào một lần tải. Xem
     * App\Services\Public\CourseService::classDetailData().
     */
    public function classDetail(Request $request, int $class): View
    {
        return view(
            'public.courses._class-detail',
            $this->courseService->classDetailData($class, $request->user()),
        );
    }

    public function show(Request $request, int $course): View
    {
        return view('public.courses.show', $this->courseService->showData($course, $request->user()));
    }
}

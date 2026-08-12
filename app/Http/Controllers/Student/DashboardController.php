<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STU-01 — trong 5 giây học sinh phải thấy: việc cần làm hôm nay, bài
 * đang dở/mới mở, lịch sắp tới, tiến độ lớp/khóa, kết quả gần đây, thông
 * báo quan trọng (10.1).
 *
 * $todayTasks/$upcoming/$notifications hiện CHƯA có bảng nghiệp vụ riêng
 * để tổng hợp (cần bảng notifications + join assignments/class_sessions
 * theo hạn) — để mock có chú thích TODO; $hasAnyClass/$classProgress/
 * $recentResults đã lấy DỮ LIỆU THẬT từ ClassEnrollment/Attempt.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('student.dashboard', $this->dashboardService->buildDashboardData($user));
    }
}

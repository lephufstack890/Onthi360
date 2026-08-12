<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function index(Request $request): View
    {
        $user = Auth::user();

        $enrollments = $user->classEnrollments()
            ->where('status', 'active')
            ->with('classRoom.course')
            ->get();

        $hasAnyClass = $enrollments->isNotEmpty();

        $classProgress = $enrollments->map(function ($enrollment) {
            $classRoom = $enrollment->classRoom;

            return [
                'name' => trim(($classRoom->course->title ?? '').' · '.($classRoom->name ?? '')),
                // TODO: tính % thật theo progress_unlocks đã hoàn thành / tổng mã bài đã mở.
                'percent' => 50,
            ];
        })->values()->all();

        $recentResults = $user->attempts()
            ->with('assessment')
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn ($attempt) => [
                'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                'score' => $attempt->total_score !== null ? (string) $attempt->total_score : 'Đang chấm',
                'time' => $attempt->submitted_at?->diffForHumans(),
                'tone' => $attempt->is_provisional ? 'info' : 'success',
            ])->all();

        // TODO: thay bằng dữ liệu thật khi có bảng notifications và luật
        // gộp assignment/progress_unlock sắp tới hạn (16 mục 4, 16 mục 9).
        $todayTasks = [];
        $upcoming = [];
        $notifications = [];

        return view('student.dashboard', [
            'name' => $user->name,
            'hasAnyClass' => $hasAnyClass,
            'todayTasks' => $todayTasks,
            'upcoming' => $upcoming,
            'classProgress' => $classProgress,
            'recentResults' => $recentResults,
            'notifications' => $notifications,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Parent;

use App\Enums\ParentLinkStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** parent.dashboard (PAR-01) — chỉ hiển thị con đã liên kết + xác minh (10.3). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $links = $user->childLinks()->where('status', ParentLinkStatus::Verified)->with('student')->get();

        $children = $links->map(function ($link) {
            $child = $link->student;
            $enrollment = $child->classEnrollments()->where('status', 'active')->with('classRoom')->first();
            $classRoom = $enrollment?->classRoom;
            $nextSession = $classRoom?->sessions()->where('starts_at', '>=', now())->orderBy('starts_at')->first();

            $totalSessions = $classRoom
                ? $classRoom->sessions()->where('starts_at', '<', now())->count()
                : 0;
            $presentSessions = $classRoom
                ? \App\Models\Attendance::where('student_id', $child->id)
                    ->whereIn('status', ['present', 'late'])
                    ->whereHas('classSession', fn ($q) => $q->where('class_room_id', $classRoom->id))
                    ->count()
                : 0;

            return [
                'id' => $child->id,
                'name' => $child->name,
                'class' => $classRoom->name ?? 'Chưa có lớp',
                'nextSession' => $nextSession?->starts_at->format('d/m H:i') ?? 'Chưa có buổi học sắp tới',
                'attendance' => $totalSessions > 0 ? "{$presentSessions}/{$totalSessions} buổi" : 'Chưa có dữ liệu',
                // TODO: % tiến độ thật cần công thức tổng hợp progress_unlocks + attempts theo lớp.
                'progress' => 0,
            ];
        })->values()->all();

        $recentResults = [];
        foreach ($links as $link) {
            $child = $link->student;
            $attempts = $child->attempts()->whereNotNull('submitted_at')->with('assessment')->latest('submitted_at')->limit(3)->get();
            foreach ($attempts as $attempt) {
                $recentResults[] = [
                    'child' => $child->name,
                    'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                    'score' => $attempt->total_score !== null ? (string) $attempt->total_score : 'Đang chấm',
                    'tone' => $attempt->is_provisional ? 'info' : 'success',
                    'time' => $attempt->submitted_at?->diffForHumans(),
                ];
            }
        }

        return view('parent.dashboard', ['children' => $children, 'recentResults' => $recentResults]);
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** teacher.dashboard (TEA-01). */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $classRoomIds = $user->classRoomsTeaching()->pluck('class_rooms.id');

        $upcoming = \App\Models\ClassSession::whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '>=', now())
            ->with('classRoom')
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'time' => $s->starts_at->format('d/m H:i'),
                'class' => $s->classRoom->name ?? '',
                'topic' => $s->topic ?? '',
            ])->all();

        $toOpen = \App\Models\Assignment::whereIn('class_room_id', $classRoomIds)
            ->whereIn('status', ['draft', 'scheduled'])
            ->with('assessment', 'classRoom')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'title' => $a->assessment->title ?? 'Bài tập',
                'class' => $a->classRoom->name ?? '',
                'chapter' => '', // TODO: chưa có khái niệm "chương" trong schema hiện tại.
            ])->all();

        // TODO: cần quy tắc tổng hợp thật (chưa nộp N bài liên tiếp / điểm giảm N bài gần nhất) —
        // để trong App\Services khi có, hiện trả rỗng để không hiển thị dữ liệu giả.
        $attentionStudents = [];

        // TODO: nối AccessRight thật của giáo viên (scope=teacher_teaching) sắp hết hạn (7.2).
        $accessExpiring = null;

        return view('teacher.dashboard', [
            'name' => $user->name,
            'upcoming' => $upcoming,
            'toOpen' => $toOpen,
            'attentionStudents' => $attentionStudents,
            'accessExpiring' => $accessExpiring,
        ]);
    }
}

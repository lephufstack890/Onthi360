<?php

namespace App\Http\Controllers\Parent;

use App\Enums\ParentLinkStatus;
use App\Http\Controllers\Controller;
use App\Models\ParentLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChildController extends Controller
{
    /** parent.children.index — chỉ hiển thị học sinh đã liên kết, không cho tìm kiếm học sinh khác (10.3, 3.3). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $children = $user->childLinks()->with('student')->get()->map(fn ($link) => [
            'id' => $link->student->id,
            'name' => $link->student->name,
            'class' => $link->student->classEnrollments()->where('status', 'active')->with('classRoom')->first()?->classRoom?->name ?? 'Chưa có lớp',
            'status' => $link->isVerified() ? 'Đã xác minh' : ($link->status === \App\Enums\ParentLinkStatus::Pending ? 'Chờ xác minh' : 'Đã hủy liên kết'),
            'tone' => $link->isVerified() ? 'success' : ($link->status === \App\Enums\ParentLinkStatus::Pending ? 'warning' : 'neutral'),
        ])->all();

        return view('parent.children.index', ['children' => $children]);
    }

    /** parent.children.show (PAR-02) — lịch/điểm danh/kết quả/tiến độ/review (10.3, 9.2). */
    public function show(Request $request, int $child): View
    {
        $user = Auth::user();

        $link = ParentLink::where('parent_user_id', $user->id)
            ->where('student_user_id', $child)
            ->where('status', ParentLinkStatus::Verified)
            ->with('student')
            ->firstOrFail();

        $childUser = $link->student;
        $enrollment = $childUser->classEnrollments()->where('status', 'active')->with('classRoom.course')->first();
        $classRoom = $enrollment?->classRoom;

        $tab = $request->query('tab', 'overview');
        $tabDefs = ['overview' => 'Tổng quan', 'schedule' => 'Lịch & Điểm danh', 'results' => 'Kết quả & Tiến độ', 'review' => 'Đánh giá lớp'];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = ['label' => $label, 'href' => route('parent.children.show', ['child' => $childUser->id, 'tab' => $key]), 'active' => $tab === $key];
        }

        $results = [];
        if ($tab === 'results' || $tab === 'overview') {
            $results = $childUser->attempts()->whereNotNull('submitted_at')->with('assessment')->latest('submitted_at')->limit(10)->get()
                ->map(fn ($a) => [
                    'title' => $a->assessment->title ?? 'Bài đã nộp',
                    'score' => $a->total_score !== null ? (string) $a->total_score : 'Đang chấm',
                    'tone' => $a->is_provisional ? 'info' : 'success',
                    'time' => $a->submitted_at?->diffForHumans(),
                ])->all();
        }

        $attendance = [];
        if ($tab === 'schedule' && $classRoom) {
            $attendance = \App\Models\Attendance::where('student_id', $childUser->id)
                ->whereHas('classSession', fn ($q) => $q->where('class_room_id', $classRoom->id))
                ->with('classSession')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'date' => $a->classSession->starts_at?->format('d/m/Y') ?? '',
                    'status' => match ($a->status->value) {
                        'present' => 'Có mặt',
                        'late' => 'Đi muộn',
                        'excused' => 'Vắng có phép',
                        'absent' => 'Vắng không phép',
                        default => $a->status->value,
                    },
                    'tone' => match ($a->status->value) {
                        'present' => 'success',
                        'late' => 'info',
                        'excused' => 'warning',
                        'absent' => 'danger',
                        default => 'neutral',
                    },
                ])->all();
        }

        $nextSession = $classRoom?->sessions()->where('starts_at', '>=', now())->orderBy('starts_at')->first();

        return view('parent.children.show', [
            'child' => $childUser,
            'classRoom' => $classRoom,
            'tab' => $tab,
            'tabsData' => $tabsData,
            'results' => $results,
            'attendance' => $attendance,
            'nextSession' => $nextSession,
        ]);
    }
}

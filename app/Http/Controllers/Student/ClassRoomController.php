<?php

namespace App\Http\Controllers\Student;

use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClassRoomController extends Controller
{
    /** student.classes.show (STU-03) — chi tiết lớp, 7 tab. */
    public function show(Request $request, int $class): View
    {
        $user = Auth::user();

        $classRoom = ClassRoom::with(['course', 'teachers'])->findOrFail($class);

        // TODO: thay bằng App\Services\AccessGateService khi service này được tích hợp cho trang lớp (7.3).
        // Hiện tại chỉ chặn người không liên quan gì tới lớp (không phải học sinh đang học, không phải giáo viên).
        $isMember = $user->classEnrollments()->where('class_room_id', $classRoom->id)->where('status', 'active')->exists()
            || $classRoom->isTaughtBy($user)
            || $user->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN);
        abort_unless($isMember, 403);

        $tab = $request->query('tab', 'overview');

        $tabsMeta = [
            ['label' => 'Tổng quan', 'key' => 'overview'],
            ['label' => 'Lộ trình & Bài tập', 'key' => 'roadmap'],
            ['label' => 'Lịch học', 'key' => 'schedule'],
            ['label' => 'Tài liệu', 'key' => 'materials'],
            ['label' => 'Đánh giá', 'key' => 'reviews'],
            ['label' => 'Thông báo', 'key' => 'notifications'],
            ['label' => 'Thành viên', 'key' => 'members'],
        ];
        $tabsData = array_map(fn ($t) => [
            'label' => $t['label'],
            'href' => route('student.classes.show', ['class' => $classRoom->id, 'tab' => $t['key']]),
            'active' => $tab === $t['key'],
        ], $tabsMeta);

        $mainTeacher = $classRoom->teachers->firstWhere('pivot.role', 'main') ?? $classRoom->teachers->first();

        $nextSession = $classRoom->sessions()->where('starts_at', '>=', now())->orderBy('starts_at')->first();

        $enrollment = $user->classEnrollments()->where('class_room_id', $classRoom->id)->first();
        // TODO: % tiến độ thật cần công thức tổng hợp progress_unlocks + attempts của riêng học sinh này.
        $overallPercent = 0;

        $ratingSummary = \App\Models\RatingSummary::where('target_type', ReviewTargetType::ClassRoom)
            ->where('target_id', $classRoom->id)->first();

        // Lộ trình & bài tập: dùng Assignment thật của lớp (chưa có mô hình "chương" nên hiển thị dạng danh sách phẳng).
        $roadmap = [];
        if ($tab === 'roadmap' || $tab === 'overview') {
            $assignments = $classRoom->assignments()->with('assessment')->orderBy('opens_at')->get();
            $items = $assignments->map(function ($a) use ($user) {
                $attempt = $user->attempts()->where('assignment_id', $a->id)->latest('submitted_at')->first();
                $status = match (true) {
                    $a->status->value === 'draft' || $a->status->value === 'scheduled' => 'Giáo viên chưa mở',
                    $attempt !== null => 'Đã làm',
                    $a->isOpenNow() => 'Đã mở',
                    default => 'Đã đóng',
                };
                $tone = match ($status) {
                    'Giáo viên chưa mở' => 'neutral',
                    'Đã làm' => 'success',
                    'Đã mở' => 'info',
                    default => 'neutral',
                };

                return [
                    'title' => $a->assessment->title ?? 'Bài tập',
                    'type' => $a->assessment?->type?->value ?? '',
                    'status' => $status,
                    'tone' => $tone,
                    'result' => $attempt?->total_score !== null ? (string) $attempt->total_score : 'Chưa làm',
                ];
            })->values()->all();

            if (! empty($items)) {
                $roadmap = [['chapter' => 'Bài tập của lớp', 'items' => $items]];
            }
        }

        // Tài liệu lớp: ClassMaterial đang Active.
        $materials = [];
        if ($tab === 'materials' || $tab === 'overview') {
            $materials = $classRoom->classMaterials()->with('material')->where('status', 'active')->get();
        }

        // Lịch học: các buổi sắp tới + đã qua gần nhất.
        $sessions = [];
        if ($tab === 'schedule') {
            $sessions = $classRoom->sessions()->orderBy('starts_at')->get();
        }

        // Đánh giá lớp: review đã publish.
        $reviews = collect();
        if ($tab === 'reviews') {
            $reviews = Review::where('target_type', ReviewTargetType::ClassRoom)
                ->where('target_id', $classRoom->id)
                ->where('status', ReviewStatus::Published)
                ->with('reviewer')
                ->latest('published_at')
                ->get();
        }

        // Thành viên.
        $teachers = $tab === 'members' ? $classRoom->teachers : collect();
        $students = $tab === 'members' ? $classRoom->students : collect();

        return view('student.classes.show', [
            'classRoom' => $classRoom,
            'tab' => $tab,
            'tabsData' => $tabsData,
            'mainTeacher' => $mainTeacher,
            'nextSession' => $nextSession,
            'overallPercent' => $overallPercent,
            'ratingSummary' => $ratingSummary,
            'roadmap' => $roadmap,
            'materials' => $materials,
            'sessions' => $sessions,
            'reviews' => $reviews,
            'teachers' => $teachers,
            'students' => $students,
        ]);
    }
}

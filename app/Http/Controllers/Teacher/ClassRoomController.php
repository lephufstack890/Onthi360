<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClassRoomController extends Controller
{
    /** teacher.classes.index (TEA-02) — lớp giáo viên phụ trách hoặc đồng phụ trách (8.1). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $classes = $user->classRoomsTeaching()
            ->with('course')
            ->withCount(['students' => fn ($q) => $q->wherePivot('status', 'active')])
            ->get()
            ->map(function ($classRoom) {
                $nextSession = $classRoom->sessions()->where('starts_at', '>=', now())->orderBy('starts_at')->first();

                return [
                    'id' => $classRoom->id,
                    'course' => $classRoom->course->title ?? '',
                    'name' => $classRoom->name,
                    'students' => $classRoom->students_count,
                    'nextSession' => $nextSession?->starts_at->format('d/m H:i'),
                    // TODO: % hoàn thành chung thật cần tổng hợp progress_unlocks + attempts toàn lớp.
                    'completion' => 0,
                ];
            })->values()->all();

        return view('teacher.classes.index', ['classes' => $classes]);
    }

    /** teacher.classes.show (TEA-02 chi tiết + TEA-06 học liệu, 8.2/8.3). */
    public function show(Request $request, int $class): View
    {
        $user = Auth::user();
        $classRoom = ClassRoom::with('course')->findOrFail($class);

        abort_unless($classRoom->isTaughtBy($user) || $user->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN), 403);

        $tab = $request->query('tab', 'overview');
        $tabDefs = ['overview' => 'Tổng quan', 'materials' => 'Học liệu', 'schedule' => 'Lịch/Điểm danh', 'assign' => 'Giao đề', 'results' => 'Kết quả', 'members' => 'Thành viên'];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = ['label' => $label, 'href' => route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => $key]), 'active' => $tab === $key];
        }

        $studentsCount = $classRoom->students()->count();
        $nextSession = $classRoom->sessions()->where('starts_at', '>=', now())->orderBy('starts_at')->first();

        $materials = [];
        if ($tab === 'materials') {
            $materials = $classRoom->classMaterials()->with('material', 'product')->where('status', 'active')->get()
                ->map(fn ($cm) => [
                    'title' => $cm->material->title ?? 'Học liệu',
                    'scope' => 'Đang dùng ở lớp này',
                    'tone' => 'success',
                    'linkedStatus' => 'Đang dùng',
                ])->all();
        }

        $members = $tab === 'members' ? $classRoom->students : collect();

        // TODO: rating_summaries theo target_type=class_room cho block "Rating nội bộ" ở tab overview.
        $ratingSummary = \App\Models\RatingSummary::where('target_type', 'class_room')->where('target_id', $classRoom->id)->first();

        return view('teacher.classes.show', [
            'classRoom' => $classRoom,
            'tab' => $tab,
            'tabsData' => $tabsData,
            'studentsCount' => $studentsCount,
            'nextSession' => $nextSession,
            'materials' => $materials,
            'members' => $members,
            'ratingSummary' => $ratingSummary,
        ]);
    }
}

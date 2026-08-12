<?php

namespace App\Http\Controllers\Student;

use App\Enums\AssessmentType;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PracticeController extends Controller
{
    /** student.practice.index (STU-04) — tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử. */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'self');

        $classRoomIds = $user->classEnrollments()->where('status', 'active')->pluck('class_room_id');

        $counts = [
            'self' => Assessment::where('type', AssessmentType::Practice)->where('status', ContentStatus::Published)->count(),
            'class' => \App\Models\Assignment::whereIn('class_room_id', $classRoomIds)->count(),
            'assigned' => \App\Models\Assignment::whereIn('class_room_id', $classRoomIds)->where('status', 'open')->count(),
            'saved' => 0, // TODO: chưa có bảng "đã lưu/bookmark".
            'history' => $user->attempts()->whereNotNull('submitted_at')->count(),
        ];

        $tabs = [
            ['label' => 'Tự luyện', 'href' => route('student.practice.index'), 'active' => $tab === 'self', 'count' => $counts['self']],
            ['label' => 'Theo lớp', 'href' => route('student.practice.index', ['tab' => 'class']), 'active' => $tab === 'class', 'count' => $counts['class']],
            ['label' => 'Bài được giao', 'href' => route('student.practice.index', ['tab' => 'assigned']), 'active' => $tab === 'assigned', 'count' => $counts['assigned']],
            ['label' => 'Đã lưu', 'href' => route('student.practice.index', ['tab' => 'saved']), 'active' => $tab === 'saved', 'count' => $counts['saved']],
            ['label' => 'Lịch sử', 'href' => route('student.practice.index', ['tab' => 'history']), 'active' => $tab === 'history', 'count' => $counts['history']],
        ];

        $items = match ($tab) {
            'class' => \App\Models\Assignment::whereIn('class_room_id', $classRoomIds)
                ->with('assessment', 'classRoom.course')
                ->latest('opens_at')->limit(30)->get()
                ->map(fn ($a) => [
                    'title' => $a->assessment->title ?? 'Bài tập',
                    'type' => $a->assessment?->type?->value ?? '',
                    'source' => 'Lớp '.($a->classRoom->name ?? ''),
                    'difficulty' => '',
                    'status' => $a->isOpenNow() ? 'Đã mở' : 'Đã đóng',
                    'tone' => $a->isOpenNow() ? 'success' : 'neutral',
                    'takeRoute' => route('student.assessment.take', $a->assessment_id),
                ])->all(),
            'assigned' => \App\Models\Assignment::whereIn('class_room_id', $classRoomIds)
                ->where('status', 'open')
                ->with('assessment', 'classRoom')
                ->orderBy('due_at')->limit(30)->get()
                ->map(fn ($a) => [
                    'title' => $a->assessment->title ?? 'Bài tập',
                    'type' => $a->assessment?->type?->value ?? '',
                    'source' => 'Lớp '.($a->classRoom->name ?? ''),
                    'difficulty' => '',
                    'status' => $a->due_at ? 'Hạn: '.$a->due_at->format('d/m H:i') : 'Đang mở',
                    'tone' => 'warning',
                    'takeRoute' => route('student.assessment.take', $a->assessment_id),
                ])->all(),
            'saved' => [], // TODO: chưa có bảng "đã lưu/bookmark".
            'history' => $user->attempts()->whereNotNull('submitted_at')->with('assessment')
                ->latest('submitted_at')->limit(30)->get()
                ->map(fn ($attempt) => [
                    'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                    'type' => $attempt->assessment?->type?->value ?? '',
                    'source' => ucfirst($attempt->source?->value ?? ''),
                    'difficulty' => '',
                    'status' => $attempt->total_score !== null ? 'Đã nộp — '.$attempt->total_score : 'Đang chấm',
                    'tone' => $attempt->is_provisional ? 'info' : 'success',
                    'takeRoute' => route('student.assessment.result', $attempt->id),
                ])->all(),
            default => Assessment::where('type', AssessmentType::Practice)
                ->where('status', ContentStatus::Published)
                ->latest()->limit(30)->get()
                ->map(fn ($a) => [
                    'title' => $a->title,
                    'type' => $a->type->value,
                    'source' => 'Tự luyện',
                    'difficulty' => '',
                    'status' => 'Chưa làm',
                    'tone' => 'info',
                    'takeRoute' => route('student.assessment.take', $a->id),
                ])->all(),
        };

        return view('student.practice.index', ['tab' => $tab, 'tabs' => $tabs, 'items' => $items]);
    }
}

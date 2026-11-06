<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ClassSpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 6/11 (khách: "lớp là một giao diện riêng, 8 tab") — các thao tác MỚI trong không gian lớp: giáo viên đứng lớp cùng,
 * sổ điểm danh từng ô + xuất CSV, nhắc nộp bài, tiến trình dạy học, chấm điểm / nhận xét, phiếu kết quả. Mỗi thao tác kiểm
 * tra quyền theo vai trò trong lớp (ClassSpaceService::authorize). Trang lớp vẫn là teacher.classes.show (ClassRoomController).
 */
class ClassSpaceController extends Controller
{
    public function __construct(private readonly ClassSpaceService $space) {}

    private function back(int $class, string $tab): RedirectResponse
    {
        return redirect()->route('teacher.classes.show', ['class' => $class, 'tab' => $tab]);
    }

    // ── Người tổ chức ──

    public function searchTeachers(Request $request, int $class): JsonResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'teachers');

        return response()->json(['items' => $this->space->searchTeachers($classRoom, (string) $request->query('q', ''))]);
    }

    public function addTeacher(Request $request, int $class): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'teachers');
        $data = $request->validate(['teacher_id' => ['required', 'integer'], 'role' => ['required', 'in:co_teacher,assistant']]);
        try {
            $t = $this->space->addTeacher($classRoom, Auth::user(), (int) $data['teacher_id'], $data['role']);
        } catch (ValidationException $e) {
            return $this->back($class, 'organizers')->withErrors($e->errors());
        }

        return $this->back($class, 'organizers')->with('space_ok', 'Đã thêm '.$t->name.' vào lớp.');
    }

    public function setTeacherRole(Request $request, int $class, int $teacher): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'teachers');
        $data = $request->validate(['role' => ['required', 'in:co_teacher,assistant']]);
        try {
            $this->space->setTeacherRole($classRoom, $teacher, $data['role']);
        } catch (ValidationException $e) {
            return $this->back($class, 'organizers')->withErrors($e->errors());
        }

        return $this->back($class, 'organizers')->with('space_ok', 'Đã đổi vai trò.');
    }

    public function removeTeacher(int $class, int $teacher): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'teachers');
        try {
            $this->space->removeTeacher($classRoom, Auth::user(), $teacher);
        } catch (ValidationException $e) {
            return $this->back($class, 'organizers')->withErrors($e->errors());
        }

        return $this->back($class, 'organizers')->with('space_ok', 'Đã gỡ giáo viên khỏi lớp.');
    }

    // ── Điểm danh ──

    public function saveAttendanceCell(Request $request, int $class): JsonResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'attendance');
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'status' => ['nullable', 'in:present,late,excused,absent'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cell = $this->space->saveAttendanceCell($classRoom, Auth::user(), (int) $data['session_id'], (int) $data['student_id'],
            $data['status'] ?? null, $request->has('note') ? (string) ($data['note'] ?? '') : null);

        return response()->json(['ok' => true, 'cell' => $cell]);
    }

    public function exportAttendance(Request $request, int $class): Response
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'attendance');
        $csv = $this->space->attendanceCsv($classRoom, $request->only(['range', 'from', 'to']));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="so-diem-danh-'.Str::slug($classRoom->name).'.csv"',
        ]);
    }

    // ── Bài tập / đề ──

    public function remind(int $class, int $assignment): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'materials');
        try {
            $n = $this->space->remindAssignment($classRoom, Auth::user(), $assignment);
        } catch (ValidationException $e) {
            return $this->back($class, 'assign')->withErrors($e->errors());
        }

        return $this->back($class, 'assign')->with('space_ok', $n > 0 ? "Đã nhắc {$n} học sinh chưa nộp bài." : 'Cả lớp đã nộp bài này rồi.');
    }

    // ── Tiến trình dạy học ──

    private function stepRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unlock_on' => ['nullable', 'date_format:Y-m-d'],
            'material_ids' => ['nullable', 'array', 'max:50'],
            'material_ids.*' => ['integer'],
            'assignment_ids' => ['nullable', 'array', 'max:50'],
            'assignment_ids.*' => ['integer'],
        ];
    }

    public function storeStep(Request $request, int $class): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'progress');
        $this->space->saveStep($classRoom, Auth::user(), $request->validate($this->stepRules()));

        return $this->back($class, 'progress')->with('space_ok', 'Đã thêm bước vào tiến trình.');
    }

    public function updateStep(Request $request, int $class, int $step): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'progress');
        $this->space->saveStep($classRoom, Auth::user(), $request->validate($this->stepRules()), $step);

        return $this->back($class, 'progress')->with('space_ok', 'Đã lưu bước.');
    }

    public function destroyStep(int $class, int $step): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'progress');
        $this->space->deleteStep($classRoom, $step);

        return $this->back($class, 'progress')->with('space_ok', 'Đã xoá bước.');
    }

    public function toggleStep(Request $request, int $class, int $step): RedirectResponse|JsonResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'progress');
        $open = $this->space->toggleStep($classRoom, $step, $request->boolean('notify', true));
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'open' => $open]);
        }

        return $this->back($class, 'progress')->with('space_ok', $open ? 'Đã mở khoá bước — học sinh thấy ngay.' : 'Đã khoá lại bước.');
    }

    public function moveStep(Request $request, int $class, int $step): RedirectResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'progress');
        $this->space->moveStep($classRoom, $step, $request->input('dir') === 'up' ? 'up' : 'down');

        return $this->back($class, 'progress');
    }

    // ── Kết quả học tập ──

    public function saveGrade(Request $request, int $class): JsonResponse
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'grade');
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'score' => ['nullable', 'numeric', 'between:0,10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $res = $this->space->saveGrade($classRoom, Auth::user(), (int) $data['student_id'],
            isset($data['assignment_id']) ? (int) $data['assignment_id'] : null,
            isset($data['score']) && $data['score'] !== '' ? round((float) $data['score'], 1) : null,
            $data['comment'] ?? null);

        return response()->json(['ok' => true] + $res);
    }

    public function exportResults(int $class): Response
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'grade');

        return response($this->space->resultsCsv($classRoom), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ket-qua-hoc-tap-'.Str::slug($classRoom->name).'.csv"',
        ]);
    }

    /** Phiếu kết quả — ?student=<id> một em, không có thì cả lớp (mỗi em 1 trang khi in). */
    public function printResults(Request $request, int $class): View
    {
        $classRoom = $this->space->authorize($class, Auth::user(), 'grade');
        $student = $request->query('student') !== null ? (int) $request->query('student') : null;

        return view('teacher.classes.report-card', $this->space->reportCards($classRoom, $student));
    }
}

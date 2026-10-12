<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Public\PracticeAssignmentService;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 12/10 — mục "Bài & đề" của khu Giáo viên (TeacherWorkPanel + TeacherAssignForm của source mới):
 * danh sách lượt giao từng học sinh, chi tiết lượt giao, bài nộp, nhận xét và form "Giao bài / đề".
 * Việc giao bài vẫn đi qua POST practice.assign (PracticeAssignmentService::assignMany) — không viết lại.
 */
class WorkController extends Controller
{
    public function __construct(
        private readonly TeacherWorkspaceService $workspace,
        private readonly PracticeAssignmentService $practice,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $rows = $this->workspace->workRows($user);
        $classes = $this->workspace->classOptions($user);

        return view('teacher.work.index', [
            'rows' => $rows,
            'classes' => $classes,
            'roster' => $this->workspace->roster(array_column($classes, 'id'), $rows),
            'catalog' => $this->workspace->catalog(),
            'scope' => $this->practice->scopeFor($user),
            'initialStatus' => in_array($request->query('status'), ['all', 'unsubmitted', 'overdue', 'pending', 'completed'], true) ? $request->query('status') : 'all',
            'openAssign' => $request->boolean('assign'),
        ]);
    }

    /** Lưu nhận xét cho một lượt giao đã có bài nộp (gọi bằng fetch, trả JSON). */
    public function feedback(Request $request, int $assignment): JsonResponse
    {
        $data = $request->validate(['feedback' => ['nullable', 'string', 'max:2000']]);

        try {
            $saved = $this->workspace->saveFeedback(Auth::user(), $assignment, $data['feedback'] ?? '');
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'ok' => true,
            'feedback' => $saved->feedback,
            'feedbackAt' => $saved->feedback_at?->format('d/m/Y H:i'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ResultService;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(
        private readonly ResultService $resultService,
        private readonly TeacherWorkspaceService $workspace,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $requestedClassId = $request->query('class') !== null ? (int) $request->query('class') : null;
        $requestedAssignmentId = $request->query('assessment') !== null ? (int) $request->query('assessment') : null;
        $requestedStatus = $request->query('status') ?: null;

        // SỬA 12/10 — mục "Kết quả & nhận xét" theo source mới: tab "Bài nộp & nhận xét" (lượt giao riêng) + tab "Theo lớp học" (phễu cũ).
        $hasFunnelFilter = $request->query('class') !== null || $request->query('assessment') !== null || $request->query('status') !== null;
        $tab = $request->query('tab') === 'class' || $hasFunnelFilter ? 'class' : 'work';

        return view('teacher.results.index', $this->resultService->funnelFor($user, $requestedClassId, $requestedAssignmentId, $requestedStatus) + [
            'tab' => $tab,
            'workRows' => array_values(array_filter($this->workspace->workRows($user), fn ($r) => in_array($r['status'], ['completed', 'pending'], true))),
            'initialStatus' => in_array($request->query('wstatus'), ['all', 'pending', 'completed'], true) ? $request->query('wstatus') : 'all',
        ]);
    }

    public function attempt(int $attempt): View
    {
        $user = Auth::user();

        return view('teacher.results.attempt', $this->resultService->attemptDetailFor($user, $attempt));
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();
        $requestedClassId = $request->query('class') !== null ? (int) $request->query('class') : null;
        $requestedAssignmentId = $request->query('assessment') !== null ? (int) $request->query('assessment') : null;
        $requestedStatus = $request->query('status') ?: null;

        $csv = $this->resultService->exportCsv($user, $requestedClassId, $requestedAssignmentId, $requestedStatus);
        $filename = 'ket-qua-'.now()->format('Y-m-d-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

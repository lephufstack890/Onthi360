<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ResultService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(private readonly ResultService $resultService) {}

    /** teacher.results.index (TEA-08) — phễu Lớp → Đề → Học sinh → Lần nộp (10.2). */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $requestedClassId = $request->query('class') !== null ? (int) $request->query('class') : null;
        $requestedAssignmentId = $request->query('assessment') !== null ? (int) $request->query('assessment') : null;
        $requestedStatus = $request->query('status') ?: null;

        return view('teacher.results.index', $this->resultService->funnelFor($user, $requestedClassId, $requestedAssignmentId, $requestedStatus));
    }

    /** teacher.results.attempt — xem chi tiết một lần nộp cụ thể (10.2, TEA-08). */
    public function attempt(int $attempt): View
    {
        $user = Auth::user();

        return view('teacher.results.attempt', $this->resultService->attemptDetailFor($user, $attempt));
    }

    /** teacher.results.export — xuất CSV kết quả theo bộ lọc hiện tại (10.2). */
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

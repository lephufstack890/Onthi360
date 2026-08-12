<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ResultService;
use Illuminate\Http\Request;
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

        return view('teacher.results.index', $this->resultService->funnelFor($user, $requestedClassId, $requestedAssignmentId));
    }
}

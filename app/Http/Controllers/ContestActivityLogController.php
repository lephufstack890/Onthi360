<?php

namespace App\Http\Controllers;

use App\Services\Contest\ContestActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SỬA 30/10 — tab "Nhật ký" của một kỳ thi / kỳ khảo sát (route competitions.activity, cần đăng nhập). Trả JSON; quyền xem
 * (ban tổ chức: mọi lượt · người khác: chỉ lượt của mình) nằm trong ContestActivityLogService::forRound().
 */
class ContestActivityLogController extends Controller
{
    public function __construct(private ContestActivityLogService $logs) {}

    public function __invoke(Request $request, int $competition, int $exam): JsonResponse
    {
        return response()->json($this->logs->forRound($request->user(), $competition, $exam))
            ->header('Cache-Control', 'no-store');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Contest\ContestScoreSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SỬA 30/10 — dữ liệu hộp "In bảng điểm & xếp giải" của một kỳ (route competitions.manage.scoreSheet, JSON). Chỉ ban tổ chức
 * của kỳ (kiểm trong ContestScoreSheetService::forRound()).
 */
class ContestScoreSheetController extends Controller
{
    public function __construct(private ContestScoreSheetService $sheets) {}

    public function __invoke(Request $request, int $competition, int $exam): JsonResponse
    {
        return response()->json($this->sheets->forRound($request->user(), $competition, $exam))
            ->header('Cache-Control', 'no-store');
    }
}

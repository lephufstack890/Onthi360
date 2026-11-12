<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RankingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService) {}

    public function index(Request $request): View
    {
        // SỬA 12/11 (khách: "bỏ tab danh sách đi, không cần") — mục Bảng xếp hạng trong admin giờ mở thẳng màn
        // Tài khoản vi phạm (cấu hình xử phạt). Màn chi tiết từng bảng (ranking.show) vẫn giữ nguyên.
        return app(LeaderboardPenaltyController::class)->index($request);
    }

    public function show(string $scope, int $id): View
    {
        return view('admin.ranking.show', $this->rankingService->showBoard($scope, $id));
    }
}

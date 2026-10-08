<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __construct(private LeaderboardService $leaderboardService) {}

    /**
     * ?scope=all-time|month|contest|class — phạm vi bảng xếp hạng (mặc định Toàn thời gian).
     * ?competition=&exam= — chọn cuộc thi/kỳ thi ở phạm vi "Cuộc thi gần nhất"; link cũ từ trang cuộc thi
     *   chỉ có 2 tham số này nên tự mở phạm vi cuộc thi.
     * ?class= — chọn lớp ở phạm vi "Lớp của tôi" khi người xem có nhiều lớp.
     */
    public function index(Request $request): View
    {
        $int = fn (string $key): ?int => $request->query($key) !== null && is_numeric($request->query($key))
            ? (int) $request->query($key)
            : null;

        $scope = $request->query('scope');

        return view(
            'public.leaderboard.index',
            $this->leaderboardService->indexData(
                is_string($scope) ? $scope : null,
                $int('competition'),
                $int('exam'),
                $int('class'),
                $request->user(),
            ),
        );
    }
}

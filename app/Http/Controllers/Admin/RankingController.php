<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RankingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService) {}

    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        // SỬA 12/11 (khách: "không cần màn riêng, gộp sang Người dùng") — khóa / mở khóa tài khoản giờ làm ngay ở danh sách
        // Người dùng; link cũ tới màn Bảng xếp hạng chuyển thẳng sang đó.
        return redirect()->route('admin.users.index');
    }

    public function show(string $scope, int $id): View
    {
        return view('admin.ranking.show', $this->rankingService->showBoard($scope, $id));
    }
}

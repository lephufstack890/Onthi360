<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Contest\ContestHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới") — 2 trang công khai dùng ContestHubService:
 *   /cuoc-thi        danh sách "Cuộc thi & Khảo sát năng lực" (lọc tên / lộ trình / loại; ?loai=khao-sat|cuoc-thi)
 *   /cuoc-thi/{id}   phòng thi (?vong=<id kỳ thi>&tab=<overview|submissions|results|ranking|registrations>)
 * Bản cũ (App\Services\Public\CompetitionService) vẫn giữ cho trang chủ (upcomingData).
 */
class CompetitionController extends Controller
{
    public function __construct(private ContestHubService $hub) {}

    public function index(Request $request): View
    {
        $type = match ((string) $request->query('loai', '')) {
            'khao-sat', 'survey' => 'survey',
            'cuoc-thi', 'contest' => 'contest',
            default => null,
        };

        return view('public.competitions.index', $this->hub->catalogData(Auth::user(), $type));
    }

    public function show(Request $request, int $competition): View
    {
        $roundId = $request->integer('vong') ?: null;
        $tab = $request->query('tab');

        // SỬA 24/10 (khách: "đang làm nửa chừng mà thoát ra là reset lại từ đầu") — về phòng thi là rời bài khảo sát:
        // bỏ dấu đã nhập mật khẩu, muốn làm phải bắt đầu lại (lượt dở bị huỷ ở ContestEntryService::enter()).
        if (Auth::check()) {
            \App\Support\ContestEntryPass::revoke(\App\Models\CompetitionExam::query()->where('competition_id', $competition)->pluck('id')->all());
        }

        return view('public.competitions.show', $this->hub->roomData($competition, Auth::user(), $roundId, is_string($tab) ? $tab : null));
    }
}

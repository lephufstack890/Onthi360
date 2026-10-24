<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Services\Contest\ContestHubService;
use App\Services\Contest\ContestOrganizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 24/10 — thao tác của BAN TỔ CHỨC ngay trong phòng thi (tab "Học sinh"): tìm & thêm học sinh, duyệt / từ chối
 * đăng ký. Route chỉ cần đăng nhập; quyền thật kiểm ở ContestOrganizerService::canManage() (admin hoặc giáo viên
 * tổ chức của cuộc thi) — người khác gọi thẳng sẽ nhận 403.
 */
class ContestManageController extends Controller
{
    public function __construct(
        private readonly ContestOrganizerService $organizers,
        private readonly ContestHubService $hub,
    ) {}

    /** GET JSON — hộp thoại "Thêm học sinh" tìm theo tên / tài khoản / lớp. */
    public function students(Request $request, int $competition): JsonResponse
    {
        $model = Competition::query()->with('advisors:id,name')->findOrFail($competition);
        $classId = $request->integer('lop') ?: null;

        return response()->json([
            'students' => $this->organizers->searchStudents($request->user(), $model, mb_substr((string) $request->query('q', ''), 0, 100), $classId),
        ]);
    }

    public function add(Request $request, int $competition): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'max:500'],
            'student_ids.*' => ['integer'],
        ], ['student_ids.required' => 'Chọn ít nhất một học sinh.']);

        $model = Competition::query()->with(['advisors:id,name', 'examSittings'])->findOrFail($competition);

        try {
            $r = $this->organizers->addStudents($request->user(), $model, $data['student_ids'], $this->hub->eventState($model) === 'completed');
        } catch (ValidationException $e) {
            return $this->back($request, $competition)->withErrors($e->errors());
        }

        $message = 'Đã thêm '.$r['added'].' học sinh'.($r['approved'] ? ', duyệt '.$r['approved'].' đăng ký có sẵn' : '')
            .($r['skipped'] ? '; bỏ qua '.$r['skipped'].' học sinh đã có trong danh sách' : '').'.';

        return $this->back($request, $competition)->with('contest_notice', $message);
    }

    public function approve(Request $request, int $competition, int $registration): RedirectResponse
    {
        return $this->review($request, $competition, $registration, true);
    }

    public function reject(Request $request, int $competition, int $registration): RedirectResponse
    {
        return $this->review($request, $competition, $registration, false);
    }

    private function review(Request $request, int $competition, int $registration, bool $approve): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;
        $model = Competition::query()->with('advisors:id,name')->findOrFail($competition);

        try {
            $this->organizers->review($request->user(), $model, $registration, $approve, $reason);
        } catch (ValidationException $e) {
            return $this->back($request, $competition)->withErrors($e->errors());
        }

        return $this->back($request, $competition)->with('contest_notice', $approve ? 'Đã duyệt học sinh tham gia.' : 'Đã từ chối đăng ký.');
    }

    private function back(Request $request, int $competition): RedirectResponse
    {
        return redirect()->route('competitions.show', array_filter([
            'competition' => $competition,
            'vong' => $request->integer('vong') ?: null,
            'tab' => 'registrations',
        ]));
    }
}

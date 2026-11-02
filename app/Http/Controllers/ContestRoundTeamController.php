<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Services\Contest\ContestRoundTeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 2/11 — khối "Người tổ chức & làm bù" ở tab Tổng quan của một kỳ: thêm / gỡ giáo viên cùng tổ chức, cho học sinh làm
 * bù kỳ đã kết thúc. Route chỉ cần đăng nhập; quyền thật kiểm ở ContestRoundTeamService (người khác gọi thẳng nhận 403).
 */
class ContestRoundTeamController extends Controller
{
    public function __construct(private readonly ContestRoundTeamService $team) {}

    public function addManager(Request $request, int $competition, int $exam): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']], ['user_id.required' => 'Chọn giáo viên cần thêm.']);
        [$c, $e] = $this->load($competition, $exam);

        try {
            $name = $this->team->addManager($request->user(), $c, $e, (int) $data['user_id']);
        } catch (ValidationException $ex) {
            return $this->back($c, $e)->withErrors($ex->errors());
        }

        return $this->back($c, $e)->with('contest_notice', 'Đã thêm '.$name.' vào ban tổ chức kỳ này.');
    }

    public function removeManager(Request $request, int $competition, int $exam, int $user): RedirectResponse
    {
        [$c, $e] = $this->load($competition, $exam);

        try {
            $name = $this->team->removeManager($request->user(), $c, $e, $user);
        } catch (ValidationException $ex) {
            return $this->back($c, $e)->withErrors($ex->errors());
        }

        return $this->back($c, $e)->with('contest_notice', 'Đã gỡ '.$name.' khỏi ban tổ chức kỳ này.');
    }

    public function addMakeup(Request $request, int $competition, int $exam): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer'],
            'accounts' => ['nullable', 'string', 'max:10000'],
            'deadline_at' => ['required', 'date'],
        ], ['deadline_at.required' => 'Chọn hạn làm bù.', 'deadline_at.date' => 'Hạn làm bù không hợp lệ.']);
        [$c, $e] = $this->load($competition, $exam);

        try {
            $r = $this->team->addMakeups($request->user(), $c, $e, $data['student_ids'] ?? [], (string) ($data['accounts'] ?? ''), (string) $data['deadline_at']);
        } catch (ValidationException $ex) {
            return $this->back($c, $e)->withErrors($ex->errors())->withInput();
        }

        $parts = [];
        if ($r['added']) {
            $parts[] = 'Đã cho '.$r['added'].' học sinh làm bù';
        }
        if ($r['updated']) {
            $parts[] = 'cập nhật hạn cho '.$r['updated'].' học sinh';
        }
        $message = ($parts !== [] ? implode(', ', $parts) : 'Không thêm được học sinh nào')
            .($r['skipped'] !== [] ? '. Bỏ qua: '.implode('; ', array_slice($r['skipped'], 0, 8)).(count($r['skipped']) > 8 ? '…' : '') : '').'.';

        return $this->back($c, $e)->with('contest_notice', $message);
    }

    public function removeMakeup(Request $request, int $competition, int $exam, int $makeup): RedirectResponse
    {
        [$c, $e] = $this->load($competition, $exam);
        $name = $this->team->removeMakeup($request->user(), $c, $e, $makeup);

        return $this->back($c, $e)->with('contest_notice', 'Đã huỷ quyền làm bù của '.$name.'.');
    }

    /** @return array{0: Competition, 1: CompetitionExam} */
    private function load(int $competition, int $exam): array
    {
        $c = Competition::query()->with('advisors:id,name')->findOrFail($competition);
        $e = CompetitionExam::query()->where('competition_id', $c->id)->findOrFail($exam);

        return [$c, $e];
    }

    private function back(Competition $c, CompetitionExam $e): RedirectResponse
    {
        return redirect()->to(route('competitions.show', ['competition' => $c->id, 'vong' => $e->id, 'tab' => 'overview']).'#to-chuc-ky');
    }
}

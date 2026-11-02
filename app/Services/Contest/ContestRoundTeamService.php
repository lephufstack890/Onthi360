<?php

namespace App\Services\Contest;

use App\Models\Attempt;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionExamMakeup;
use App\Models\CompetitionRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 2/11 — 2 việc của BAN TỔ CHỨC một kỳ thi / kỳ khảo sát (khối "Người tổ chức & làm bù" ở tab Tổng quan):
 *
 *  1) GIÁO VIÊN CÙNG TỔ CHỨC (khách: "ai tổ chức kỳ thì chỉ người đó được sửa, trừ admin can thiệp tất cả; giáo viên khác
 *     muốn can thiệp phải được người tạo add vào"). Chỉ CHỦ của kỳ (người chủ trì / admin — canOwnRound()) thêm, gỡ được.
 *     Người được thêm làm được mọi việc của ban tổ chức kỳ đó (đề, học sinh, làm thử, bảng xếp hạng, nhật ký, in bảng điểm,
 *     cho làm bù) trừ xoá kỳ và thêm / gỡ người cùng tổ chức.
 *
 *  2) LÀM BÙ (khách: "kỳ kết thúc mà học sinh quên làm / chưa làm thì người tổ chức hoặc admin add vào làm rồi xếp hạng như
 *     bình thường"). Chỉ khi kỳ đã kết thúc; ai quản lý được kỳ (canManageRound()) đều thêm được. Học sinh đã nộp bài ở kỳ
 *     thì không thêm (tránh làm lại lấy điểm cao hơn). Cuộc thi: học sinh làm bù được duyệt luôn vào danh sách cuộc thi. Chọn được mọi học sinh đang hoạt động.
 *     Khảo sát: học sinh làm bù vào thẳng, không phải nhập mật khẩu.
 */
class ContestRoundTeamService
{
    /** Số học sinh tối đa đổ sẵn vào ô chọn làm bù (SỬA 2/11 — khách: "đổ hết toàn bộ danh sách học sinh"). */
    private const CANDIDATE_LIMIT = 5000;

    public function __construct(private readonly ContestOrganizerService $organizers) {}

    /** Dữ liệu cho partials/round-team (chỉ gọi khi người xem quản lý được kỳ). */
    public function panel(User $viewer, Competition $c, CompetitionExam $exam): array
    {
        $canOwn = $this->organizers->canOwnRound($viewer, $c, $exam);
        $routeArgs = ['competition' => $c->id, 'exam' => $exam->id];
        $ownerId = CompetitionExam::supportsRoundSetup() ? $exam->organizer_id : null;
        $owner = $ownerId !== null ? User::query()->find($ownerId, ['id', 'name', 'email', 'phone']) : null;

        $managers = CompetitionExam::supportsManagers()
            ? $exam->managers()->orderBy('name')->get(['users.id', 'users.name', 'users.email', 'users.phone'])
            : collect();

        $teacherOptions = [];
        if ($canOwn && CompetitionExam::supportsManagers()) {
            $skip = $managers->pluck('id')->push($ownerId)->filter()->map(fn ($v) => (int) $v)->all();
            $teacherOptions = $this->activeTeachers()
                ->whereNotIn('users.id', $skip === [] ? [0] : $skip)
                ->orderBy('name')->limit(500)->get(['users.id', 'users.name', 'users.email', 'users.phone'])
                ->map(fn (User $u) => ['id' => (int) $u->id, 'label' => $u->name.(($u->email ?: $u->phone) ? ' · '.($u->email ?: $u->phone) : '')])
                ->all();
        }

        $ended = $exam->computedStatus() === 'ended';

        return [
            'supported' => CompetitionExam::supportsManagers(),
            'canOwn' => $canOwn,
            'ownerName' => $owner?->name ?? $this->organizers->organizerLabel($c),
            'ownerIsLegacy' => $owner === null,
            'managers' => $managers->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'account' => (string) ($u->email ?: $u->phone ?: ''),
                'removeUrl' => route('competitions.manage.team.remove', $routeArgs + ['user' => $u->id]),
            ])->values()->all(),
            'teacherOptions' => $teacherOptions,
            'addUrl' => route('competitions.manage.team.add', $routeArgs),
            'makeup' => $this->makeupPanel($viewer, $c, $exam, $ended),
        ];
    }

    // ───────────────────────── Giáo viên cùng tổ chức ─────────────────────────

    public function addManager(User $actor, Competition $c, CompetitionExam $exam, int $teacherId): string
    {
        $this->assertOwner($actor, $c, $exam);
        $this->assertManagersSupported();

        $teacher = $this->activeTeachers()->where('users.id', $teacherId)->first(['users.id', 'users.name']);
        if ($teacher === null) {
            throw ValidationException::withMessages(['team' => 'Không tìm thấy giáo viên đang hoạt động này.']);
        }
        if (CompetitionExam::supportsRoundSetup() && (int) $exam->organizer_id === (int) $teacher->id) {
            throw ValidationException::withMessages(['team' => $teacher->name.' đang là người chủ trì kỳ này.']);
        }

        $exam->managers()->syncWithoutDetaching([$teacher->id => ['added_by' => $actor->id]]);

        return (string) $teacher->name;
    }

    public function removeManager(User $actor, Competition $c, CompetitionExam $exam, int $teacherId): string
    {
        $this->assertOwner($actor, $c, $exam);
        $this->assertManagersSupported();

        $name = (string) (User::query()->whereKey($teacherId)->value('name') ?? 'giáo viên');
        $exam->managers()->detach($teacherId);

        return $name;
    }

    // ───────────────────────────────── Làm bù ─────────────────────────────────

    /**
     * Thêm học sinh làm bù (tick trong danh sách + gõ tài khoản email / SĐT, mỗi dòng một tài khoản).
     *
     * @param  array<int, int|string>  $studentIds
     * @return array{added:int, updated:int, skipped:array<int, string>}
     */
    public function addMakeups(User $actor, Competition $c, CompetitionExam $exam, array $studentIds, string $accounts, string $deadline): array
    {
        $this->assertManager($actor, $c, $exam);
        if (! CompetitionExamMakeup::supported()) {
            throw ValidationException::withMessages(['makeup' => 'Máy chủ chưa chạy migration — chạy "php artisan migrate" rồi thử lại.']);
        }
        if ($exam->computedStatus() !== 'ended') {
            throw ValidationException::withMessages(['makeup' => 'Kỳ này chưa kết thúc — học sinh vẫn vào làm bình thường, chưa cần cho làm bù.']);
        }
        if ($exam->assessment_id === null && app(ContestPaperService::class)->publishedPapers($exam)->isEmpty()) {
            throw ValidationException::withMessages(['makeup' => 'Kỳ này chưa có đề — không cho làm bù được.']);
        }

        try {
            $until = Carbon::parse($deadline);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['makeup' => 'Chọn hạn làm bù hợp lệ.']);
        }
        if ($until->lte(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['makeup' => 'Hạn làm bù phải sau thời điểm hiện tại.']);
        }
        if ($until->gt(now()->addDays(60))) {
            throw ValidationException::withMessages(['makeup' => 'Hạn làm bù tối đa 60 ngày kể từ hôm nay.']);
        }

        $skipped = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        // Tài khoản gõ tay: email hoặc số điện thoại, mỗi dòng / dấu phẩy một tài khoản.
        $typed = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $accounts) ?: []))));
        if (count($typed) > 200) {
            throw ValidationException::withMessages(['makeup' => 'Mỗi lần nhập tối đa 200 tài khoản.']);
        }
        if ($typed !== []) {
            $found = $this->activeStudents()
                ->where(fn ($w) => $w->whereIn('email', $typed)->orWhereIn('phone', $typed))
                ->get(['users.id', 'users.email', 'users.phone']);
            foreach ($typed as $acc) {
                $hit = $found->first(fn (User $u) => strcasecmp((string) $u->email, $acc) === 0 || (string) $u->phone === $acc);
                if ($hit !== null) {
                    $ids[] = (int) $hit->id;
                } else {
                    $skipped[] = $acc.': không tìm thấy tài khoản học sinh';
                }
            }
            $ids = array_values(array_unique($ids));
        }
        if ($ids === []) {
            throw ValidationException::withMessages(['makeup' => $skipped !== [] ? implode('; ', $skipped).'.' : 'Chọn ít nhất một học sinh.']);
        }

        // SỬA 2/11 (khách: "đổ hết toàn bộ danh sách học sinh") — ban tổ chức kỳ cho làm bù được mọi học sinh đang hoạt động.
        $students = $this->activeStudents()->whereIn('users.id', $ids)->get(['users.id', 'users.name'])->keyBy('id');
        $submitted = array_flip($this->submittedIds($exam));

        $added = 0;
        $updated = 0;
        DB::transaction(function () use ($ids, $students, $submitted, $exam, $c, $actor, $until, &$skipped, &$added, &$updated) {
            foreach ($ids as $id) {
                $s = $students->get($id);
                if ($s === null) {
                    $skipped[] = 'Tài khoản #'.$id.': không phải học sinh đang hoạt động';

                    continue;
                }
                if (isset($submitted[$id])) {
                    $skipped[] = $s->name.': đã nộp bài kỳ này';

                    continue;
                }

                $row = CompetitionExamMakeup::query()->firstOrNew(['competition_exam_id' => $exam->id, 'student_id' => $id]);
                if ($row->exists) {
                    $updated++;
                } else {
                    $added++;
                }
                $row->fill(['added_by' => $actor->id, 'deadline_at' => $until])->save();

                if (! $c->isSurvey()) {
                    $this->approveRegistration($c, $id, $actor);
                }
                // Lượt mở trong giờ thi nhưng bỏ dở, chưa nộp: bỏ đi để học sinh làm bù một lượt mới từ đầu (không thì mở
                // lại sẽ bị tự nộp bài dở đó vì đã quá giờ đóng kỳ).
                $this->discardStaleDrafts($exam, $id);
            }
        });
        CompetitionExamMakeup::forgetMemo();

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
    }

    public function removeMakeup(User $actor, Competition $c, CompetitionExam $exam, int $makeupId): string
    {
        $this->assertManager($actor, $c, $exam);
        $row = CompetitionExamMakeup::query()->where('competition_exam_id', $exam->id)->with('student:id,name')->findOrFail($makeupId);
        $name = $row->student?->name ?? 'học sinh';
        $row->delete();
        CompetitionExamMakeup::forgetMemo();

        return $name;
    }

    /** Xoá lượt đang làm dở (chưa nộp) bắt đầu trong giờ thi của học sinh ở kỳ — cùng cách ContestEntryService. */
    private function discardStaleDrafts(CompetitionExam $exam, int $studentId): void
    {
        $ids = Attempt::query()->where('competition_exam_id', $exam->id)->where('user_id', $studentId)->whereNull('submitted_at')
            ->when($exam->ends_at !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('started_at')->orWhere('started_at', '<=', $exam->ends_at)))
            ->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        $answerIds = DB::table('attempt_answers')->whereIn('attempt_id', $ids)->pluck('id')->all();
        if ($answerIds !== [] && \Illuminate\Support\Facades\Schema::hasTable('judge_submissions')) {
            DB::table('judge_submissions')->whereIn('attempt_answer_id', $answerIds)->delete();
        }
        foreach (['attempt_answers', 'attempt_answer_keys', 'attempt_coding_items'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                DB::table($table)->whereIn('attempt_id', $ids)->delete();
            }
        }
        Attempt::query()->whereIn('id', $ids)->delete();
    }

    private function makeupPanel(User $viewer, Competition $c, CompetitionExam $exam, bool $ended): array
    {
        $routeArgs = ['competition' => $c->id, 'exam' => $exam->id];
        if (! CompetitionExamMakeup::supported()) {
            return ['supported' => false, 'ended' => $ended, 'rows' => [], 'candidates' => []];
        }

        $rows = CompetitionExamMakeup::query()->where('competition_exam_id', $exam->id)
            ->with(['student:id,name,email,phone', 'addedBy:id,name'])->orderByDesc('id')->get();
        $userIds = $rows->pluck('student_id')->map(fn ($v) => (int) $v)->all();
        $attempts = $userIds === [] ? collect() : Attempt::query()->where('competition_exam_id', $exam->id)->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'submitted_at', 'status'])->groupBy('user_id');

        $rowData = $rows->map(function (CompetitionExamMakeup $m) use ($attempts, $routeArgs) {
            $list = $attempts->get($m->student_id, collect());
            $state = match (true) {
                $list->contains(fn ($a) => $a->submitted_at !== null) => 'done',
                $list->isNotEmpty() => 'doing',
                $m->deadline_at !== null && now()->gte($m->deadline_at) => 'expired',
                default => 'waiting',
            };

            return [
                'id' => (int) $m->id,
                'name' => $m->student?->name ?? 'Tài khoản đã xoá',
                'account' => (string) ($m->student?->email ?: $m->student?->phone ?: ''),
                'deadline' => $m->deadline_at?->format('H:i d/m/Y'),
                'addedBy' => $m->addedBy?->name,
                'state' => $state,
                'stateLabel' => ['done' => 'Đã nộp bài', 'doing' => 'Đang làm', 'expired' => 'Hết hạn, chưa làm', 'waiting' => 'Chưa làm'][$state],
                'removeUrl' => route('competitions.manage.makeup.remove', $routeArgs + ['makeup' => $m->id]),
            ];
        })->values()->all();

        // SỬA 2/11 (khách: "đổ hết toàn bộ danh sách học sinh") — mọi học sinh đang hoạt động, trừ người đã nộp bài kỳ này
        // và người đã có trong danh sách làm bù.
        $candidates = [];
        if ($ended) {
            $skip = array_values(array_unique(array_merge($userIds, $this->submittedIds($exam))));
            $students = $this->activeStudents()->whereNotIn('users.id', $skip === [] ? [0] : $skip)
                ->orderBy('name')->limit(self::CANDIDATE_LIMIT)->get(['users.id', 'users.name', 'users.email', 'users.phone']);
            $classNames = $this->organizers->classNamesFor($students->pluck('id')->map(fn ($v) => (int) $v)->all());
            $candidates = $students->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'account' => (string) ($u->email ?: $u->phone ?: ''),
                'className' => $classNames[(int) $u->id] ?? '',
            ])->values()->all();
        }

        return [
            'supported' => true,
            'ended' => $ended,
            'rows' => $rowData,
            'candidates' => $candidates,
            'addUrl' => route('competitions.manage.makeup.add', $routeArgs),
            'defaultDeadline' => now()->addDay()->setTime(23, 59)->format('Y-m-d\\TH:i'),
            'minDeadline' => now()->addMinutes(10)->format('Y-m-d\\TH:i'),
        ];
    }

    private function submittedIds(CompetitionExam $exam): array
    {
        return Attempt::query()->where('competition_exam_id', $exam->id)->whereNotNull('submitted_at')
            ->distinct()->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }

    /** Cuộc thi: học sinh làm bù phải ở trạng thái đã duyệt (cửa vào thi kiểm đăng ký). */
    private function approveRegistration(Competition $c, int $studentId, User $actor): void
    {
        if (! CompetitionRegistration::supported()) {
            return;
        }
        $reg = CompetitionRegistration::query()->where('competition_id', $c->id)->where('student_id', $studentId)->first();
        if ($reg !== null && $reg->isApproved()) {
            return;
        }
        $values = [
            'status' => CompetitionRegistration::STATUS_APPROVED,
            'requested_at' => $reg?->requested_at ?? now(),
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'reject_reason' => null,
        ];
        if (CompetitionRegistration::supportsHubFields()) {
            $values['source'] = CompetitionRegistration::SOURCE_ORGANIZER;
            $values['added_by'] = $actor->id;
        }
        CompetitionRegistration::updateOrCreate(['competition_id' => $c->id, 'student_id' => $studentId], $values);
    }

    private function activeStudents()
    {
        return User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', Role::STUDENT))
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active'));
    }

    private function activeTeachers()
    {
        return User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', Role::TEACHER))
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active'));
    }

    private function assertOwner(User $actor, Competition $c, CompetitionExam $exam): void
    {
        abort_unless($this->organizers->canOwnRound($actor, $c, $exam), 403, 'Chỉ người chủ trì kỳ này (hoặc admin) được thêm / gỡ người cùng tổ chức.');
    }

    private function assertManager(User $actor, Competition $c, CompetitionExam $exam): void
    {
        abort_unless($this->organizers->canCreateRound($actor) && $this->organizers->canManageRound($actor, $c, $exam), 403,
            'Chỉ ban tổ chức của kỳ này (hoặc admin) được cho học sinh làm bù.');
    }

    private function assertManagersSupported(): void
    {
        if (! CompetitionExam::supportsManagers()) {
            throw ValidationException::withMessages(['team' => 'Máy chủ chưa chạy migration — chạy "php artisan migrate" rồi thử lại.']);
        }
    }
}

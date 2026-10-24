<?php

namespace App\Services\Contest;

use App\Enums\AttemptStatus;
use App\Enums\CompetitionStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\Role;
use App\Models\User;
use App\Support\ContestForms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới, cả admin lẫn public") — dữ liệu cho 2 trang công khai mới:
 *
 *  catalogData() → /cuoc-thi            (source: ContestsPage.jsx — "Cuộc thi & Khảo sát năng lực")
 *  roomData()    → /cuoc-thi/{id}       (source: ContestRoomPage.jsx + ContestRoomContent/Rankings/Participants.jsx)
 *
 * Phòng thi là MỘT trang cho mọi người xem: khách thấy thông tin, học sinh thấy nút đăng ký / vào thi / kết quả,
 * ban tổ chức (admin hoặc giáo viên tổ chức của cuộc thi) thấy thêm tab "Học sinh" + nút in báo cáo.
 *
 * Mọi con số đọc từ dữ liệu thật (attempts / attempt_answers / competition_registrations), không có "dữ liệu minh hoạ"
 * như bản mẫu. Bảng xếp hạng tính thẳng từ lượt nộp: cuộc thi = lượt nộp duy nhất; khảo sát = lượt điểm cao nhất
 * (bằng điểm thì ai đạt sớm hơn đứng trên) — đúng luật ghi trong contestRankings.js.
 */
class ContestHubService
{
    public const STATE_LABELS = ['current' => 'Đang diễn ra', 'upcoming' => 'Sắp diễn ra', 'completed' => 'Đã kết thúc'];

    /** Số lượt nộp tối đa đọc cho bảng xếp hạng một kỳ (đủ cho quy mô hiện tại, chặn trang nặng bất thường). */
    private const RANKING_ATTEMPT_LIMIT = 3000;

    public function __construct(
        private readonly ContestOrganizerService $organizers,
        private readonly ContestEntryService $entry,
    ) {}

    // ───────────────────────────── Danh sách ─────────────────────────────

    public function catalogData(?User $viewer, ?string $type = null): array
    {
        $hub = Competition::supportsHubFields();

        $competitions = Competition::query()
            ->where('status', '!=', CompetitionStatus::Archived->value)
            ->with(array_merge(['examSittings.assessment:id,title,duration_minutes', 'advisors:id,name'], Competition::supportsCourse() ? ['course:id,title'] : []))
            ->latest('id')
            ->limit(200)
            ->get();

        $statuses = ($viewer !== null && CompetitionRegistration::supported() && $competitions->isNotEmpty())
            ? CompetitionRegistration::query()
                ->where('student_id', $viewer->id)
                ->whereIn('competition_id', $competitions->pluck('id')->all())
                ->pluck('status', 'competition_id')->all()
            : [];

        $cards = $competitions->map(fn (Competition $c) => $this->card($c, $statuses[$c->id] ?? null))->values()->all();

        $roadmaps = collect($cards)->pluck('roadmap')->filter()->unique('id')->sortBy('title')->values()->all();

        return [
            'cards' => $cards,
            'roadmaps' => $roadmaps,
            'contestCount' => count(array_filter($cards, fn ($c) => ! $c['isSurvey'])),
            'surveyCount' => count(array_filter($cards, fn ($c) => $c['isSurvey'])),
            'initialType' => in_array($type, ['contest', 'survey'], true) ? $type : 'all',
        ];
    }

    private function card(Competition $c, ?string $registrationStatus): array
    {
        $survey = $c->isSurvey();
        $state = $this->eventState($c);
        $rounds = $c->examSittings;

        [$start, $end] = $this->eventRange($c);

        $registration = match (true) {
            $survey => ['label' => 'Tham gia bằng mật khẩu', 'tone' => 'neutral', 'icon' => 'key-round'],
            $registrationStatus === CompetitionRegistration::STATUS_APPROVED => ['label' => 'Đã được duyệt', 'tone' => 'opened', 'icon' => 'check-circle-2'],
            $registrationStatus === CompetitionRegistration::STATUS_PENDING => ['label' => 'Đang chờ duyệt', 'tone' => 'todo', 'icon' => 'user-check'],
            $registrationStatus === CompetitionRegistration::STATUS_REJECTED => ['label' => 'Đăng ký chưa được duyệt', 'tone' => 'neutral', 'icon' => 'user-check'],
            default => ['label' => 'Chưa đăng ký', 'tone' => 'neutral', 'icon' => 'user-check'],
        };

        return [
            'id' => $c->id,
            'title' => $c->title,
            'isSurvey' => $survey,
            'type' => $survey ? 'survey' : 'contest',
            'url' => route('competitions.show', $c->id),
            'image' => $c->imageUrl(),
            'state' => $state,
            'stateLabel' => $this->stateHint($state, $start, $end),
            'roadmap' => $this->roadmap($c),
            'description' => $this->description($c),
            'organizer' => $this->organizerLabel($c),
            'roundsCount' => $rounds->count(),
            'duration' => $this->durationLabel($rounds),
            // SỬA 24/10 — kèm giờ: cùng ngày mà chưa tới giờ vẫn là "Sắp diễn ra", không có giờ thì nhìn như sai.
            'startLabel' => $start?->format('H:i · d/m/Y') ?? '—',
            'endLabel' => $end?->format('H:i · d/m/Y') ?? '—',
            'registration' => $registration,
        ];
    }

    // ───────────────────────────── Phòng thi ─────────────────────────────

    public function roomData(int $competitionId, ?User $viewer, ?int $roundId = null, ?string $tab = null): array
    {
        $hub = Competition::supportsHubFields();

        /** @var Competition $c */
        $c = Competition::query()
            ->with(array_merge(['examSittings.assessment', 'advisors:id,name'], Competition::supportsCourse() ? ['course:id,title'] : []))
            ->findOrFail($competitionId);

        $survey = $c->isSurvey();
        $manager = $this->organizers->canManage($viewer, $c);
        $isStudent = $viewer !== null && $viewer->hasRole(Role::STUDENT) && ! $manager;
        $registration = $isStudent ? $this->entry->registrationFor($viewer, $c->id) : null;
        $eventState = $this->eventState($c);
        $closed = $eventState === 'completed';

        $exams = $c->examSittings->values();
        $examIds = $exams->pluck('id')->map(fn ($id) => (int) $id)->all();

        $myAttempts = ($viewer !== null && $examIds !== [])
            ? Attempt::query()->where('user_id', $viewer->id)->whereIn('competition_exam_id', $examIds)->orderBy('id')->get()->groupBy('competition_exam_id')
            : collect();

        $rounds = $exams->map(function (CompetitionExam $e, int $i) use ($myAttempts) {
            $mine = $myAttempts->get($e->id, collect());
            $state = $this->roundState($e);

            return [
                'id' => (int) $e->id,
                'no' => $i + 1,
                'noLabel' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'label' => $e->displayTitle(),
                'state' => $state,
                'stateLabel' => self::STATE_LABELS[$state],
                'dateRange' => ($e->starts_at?->format('d/m/Y') ?? 'Chưa xếp lịch').($e->ends_at ? ' – '.$e->ends_at->format('d/m/Y') : ''),
                'submitted' => $mine->contains(fn ($a) => $a->submitted_at !== null),
                'hasDraft' => $mine->contains(fn ($a) => $a->status === AttemptStatus::InProgress),
                'url' => route('competitions.show', ['competition' => $e->competition_id, 'vong' => $e->id]),
            ];
        })->all();

        $selectedExam = $this->pickRound($exams, $roundId);
        [$start, $end] = $this->eventRange($c);
        $doneCount = count(array_filter($rounds, fn ($r) => $r['submitted']));

        $base = [
            'competition' => $c,
            'contest' => [
                'id' => $c->id,
                'title' => $c->title,
                'isSurvey' => $survey,
                'image' => $c->imageUrl(),
                'editionLabel' => $this->editionLabel($c),
                'description' => $this->description($c),
                'rules' => trim((string) $c->rules),
                'organizer' => $this->organizerLabel($c),
                'roadmap' => $this->roadmap($c),
                'state' => $eventState,
                'stateLabel' => $this->stateHint($eventState, $start, $end),
                'dateRange' => ($start?->format('H:i d/m/Y') ?? 'Chưa xếp lịch').' – '.($end?->format('H:i d/m/Y') ?? '…'),
                'publishLabel' => $c->publish_result_at?->format('H:i d/m/Y'),
            ],
            'viewer' => [
                'loggedIn' => $viewer !== null,
                'isStudent' => $isStudent,
                'isManager' => $manager,
                'isAdmin' => $viewer !== null && $this->organizers->isAdmin($viewer),
                'registrationStatus' => $registration?->status,
                'name' => $viewer?->name,
            ],
            'rounds' => $rounds,
            'doneCount' => $doneCount,
            'closed' => $closed,
            'loginUrl' => route('login'),
            'catalogUrl' => route('competitions.index'),
            'adminUrl' => $viewer !== null && $this->organizers->isAdmin($viewer) ? route('admin.competitions.show', $c->id) : null,
            'serverNowMs' => (int) round(microtime(true) * 1000),
        ];

        if ($selectedExam === null) {
            return $base + ['round' => null, 'tabs' => [], 'activeTab' => 'overview', 'action' => $this->noRoundAction($manager)];
        }

        return $base + $this->roundData($c, $selectedExam, $rounds, $viewer, $registration, $manager, $isStudent, $closed, $myAttempts->get($selectedExam->id, collect()), $tab);
    }

    private function roundData(
        Competition $c,
        CompetitionExam $exam,
        array $rounds,
        ?User $viewer,
        ?CompetitionRegistration $registration,
        bool $manager,
        bool $isStudent,
        bool $closed,
        Collection $mine,
        ?string $tab,
    ): array {
        $survey = $c->isSurvey();
        $assessment = $exam->assessment;
        $period = $this->roundState($exam);
        $questions = $this->questionsOf($exam);
        $totalPoints = (float) ($assessment?->total_points ?: array_sum(array_column($questions, 'points')) ?: 100);
        $duration = $assessment?->duration_minutes ? $assessment->duration_minutes.' phút' : 'Không giới hạn';

        // Bài nộp của tôi (kỳ đang xem)
        $attempts = $mine->filter(fn (Attempt $a) => $a->submitted_at !== null)->values();
        $attemptRows = $attempts->map(fn (Attempt $a, int $i) => $this->attemptRow($a, $i, $totalPoints))->all();
        $latest = $attemptRows === [] ? null : end($attemptRows);
        $inProgress = $mine->first(fn (Attempt $a) => $a->status === AttemptStatus::InProgress);

        // Mã đề
        $isPdf = $assessment?->isPdfMode() ?? false;
        $formCount = (! $survey && ! $isPdf) ? $exam->formCount() : 1;
        $assignedForm = $this->entry->assignedForm($registration, $exam);
        $forms = $survey ? [] : array_map(fn (int $no) => [
            'no' => $no,
            'label' => $formCount > 1 ? ContestForms::label($no) : 'Đề thi chính thức',
            'questionCount' => count($questions),
            'points' => $this->num($totalPoints),
            'duration' => $duration,
        ], range(1, max(1, $formCount)));

        // Bảng xếp hạng + thống kê
        $resultsPublic = $manager || $c->publish_result_at === null || now()->gte($c->publish_result_at);
        $rankings = $this->rankings($exam, $questions, $totalPoints, $survey, $viewer, $manager, $formCount);
        $myRank = 0;
        foreach ($rankings as $i => $row) {
            if ($row['isViewer']) {
                $myRank = $i + 1;
                break;
            }
        }

        // Quyền vào thi + nút hành động (bản mẫu: contestEntry() + chuỗi if/else "action" trong ContestRoomPage.jsx)
        $resume = $inProgress !== null;
        $access = $this->access($survey, $period, $isStudent, $assessment !== null, $latest !== null, $registration?->status);
        $action = $this->action($c, $survey, $period, $closed, $manager, $viewer, $isStudent, $registration?->status, $latest !== null, $access, $resume);

        $tabs = array_values(array_filter([
            ['id' => 'overview', 'icon' => 'list-checks', 'label' => 'Thông tin kỳ thi', 'count' => null],
            $viewer !== null ? ['id' => 'submissions', 'icon' => 'file-text', 'label' => 'Bài nộp của tôi', 'count' => count($attemptRows)] : null,
            ['id' => 'results', 'icon' => $survey ? 'bar-chart-3' : 'trophy', 'label' => $survey ? 'Kết quả năng lực' : 'Bảng xếp hạng', 'count' => null],
            $survey ? ['id' => 'ranking', 'icon' => 'trophy', 'label' => 'Bảng xếp hạng', 'count' => null] : null,
            $manager ? ['id' => 'registrations', 'icon' => 'user-check', 'label' => 'Học sinh', 'count' => null] : null,
        ]));
        $tabIds = array_column($tabs, 'id');

        $participants = ($manager && $viewer !== null) ? $this->participants($c, $exam, $rankings, $viewer) : null;
        if ($participants !== null) {
            foreach ($tabs as $i => $t) {
                if ($t['id'] === 'registrations') {
                    $tabs[$i]['count'] = $participants['pending'] ?: count($participants['rows']);
                }
            }
        }

        $countdownTarget = match ($period) {
            'upcoming' => $exam->starts_at,
            'current' => $exam->ends_at,
            default => null,
        };

        return [
            'round' => [
                'id' => (int) $exam->id,
                'label' => $exam->displayTitle(),
                'state' => $period,
                'stateLabel' => self::STATE_LABELS[$period],
                'timeFrom' => $exam->starts_at?->format('H:i d/m/Y') ?? 'Chưa xếp lịch',
                'timeTo' => $exam->ends_at?->format('H:i d/m/Y') ?? 'Không giới hạn',
                'countdownLabel' => $period === 'upcoming' ? 'Mở kỳ thi sau' : ($period === 'current' ? 'Đóng kỳ thi sau' : 'Đã đóng kỳ thi'),
                'countdownTargetMs' => $countdownTarget !== null ? $countdownTarget->getTimestamp() * 1000 : null,
                'duration' => $duration,
                'questionCount' => count($questions),
                'totalPoints' => $this->num($totalPoints),
                'hasAssessment' => $assessment !== null,
                'isPdf' => $isPdf,
                'assignmentMode' => $exam->assignmentMode(),
                'formCount' => $formCount,
                'enterUrl' => route('student.competitions.enter', ['competition' => $c->id, 'exam' => $exam->id]),
                'needsPassword' => $survey && $c->hasEntryPassword(),
            ],
            'questions' => $questions,
            'questionsVisible' => $manager || ($survey && $attemptRows !== []),
            'forms' => $forms,
            'assignedForm' => $assignedForm,
            'assignedFormLabel' => $assignedForm !== null && $formCount > 1 ? ContestForms::label($assignedForm) : null,
            'attempts' => $attemptRows,
            'latest' => $latest,
            'resume' => $resume,
            'myRank' => $myRank,
            'resultsPublic' => $resultsPublic,
            'rankings' => $resultsPublic ? $rankings : [],
            'distribution' => $resultsPublic ? $this->distribution($rankings) : [],
            'questionStats' => $resultsPublic ? $this->questionStats($questions, $rankings) : [],
            'surveyResult' => $survey ? $this->surveyResult($attemptRows, $questions, $attempts) : null,
            'participants' => $participants,
            'access' => $access,
            'action' => $action,
            'tabs' => $tabs,
            'activeTab' => in_array($tab, $tabIds, true) ? $tab : 'overview',
            'rankingRule' => $survey
                ? 'Mỗi học sinh lấy điểm cao nhất trong kỳ khảo sát; bằng điểm ưu tiên thời điểm đạt điểm đó sớm hơn.'
                : 'Xếp theo tổng điểm giảm dần; bằng điểm ưu tiên thời điểm nộp sớm hơn. Mỗi học sinh có một lượt nộp trong kỳ thi.',
            'announcement' => $survey
                ? ['title' => 'Nhận mật khẩu trước khi khảo sát', 'text' => 'Liên hệ giáo viên để nhận mật khẩu. Nhập lại mật khẩu mỗi khi bắt đầu hoặc tiếp tục làm bài.']
                : ['title' => 'Hoàn tất đăng ký trước khi vào thi', 'text' => 'Sau khi đăng ký được giáo viên hoặc admin tổ chức duyệt, bạn có thể vào các kỳ thi đang mở. Mỗi kỳ thi chỉ được nộp một lần.'],
        ];
    }

    // ───────────────────────────── Luật hiển thị ─────────────────────────────

    /** @return array{allowed: bool, label: string} — bản mẫu: contestEntry(). */
    private function access(bool $survey, string $period, bool $isStudent, bool $hasAssessment, bool $submitted, ?string $registrationStatus): array
    {
        if ($period !== 'current') {
            return ['allowed' => false, 'label' => $period === 'upcoming' ? 'Chưa đến giờ thi' : 'Kỳ thi đã kết thúc'];
        }
        if (! $isStudent) {
            return ['allowed' => false, 'label' => 'Dành cho tài khoản học sinh'];
        }
        if (! $hasAssessment) {
            return ['allowed' => false, 'label' => $survey ? 'Chờ giáo viên chốt đề' : 'Kỳ thi chưa có đề'];
        }
        if ($survey) {
            return ['allowed' => true, 'label' => $submitted ? 'Làm lại khảo sát' : 'Bắt đầu khảo sát'];
        }
        if ($submitted) {
            return ['allowed' => false, 'label' => 'Đã nộp bài · Xem kết quả'];
        }

        return $registrationStatus === CompetitionRegistration::STATUS_APPROVED
            ? ['allowed' => true, 'label' => 'Vào thi']
            : ['allowed' => false, 'label' => $registrationStatus === CompetitionRegistration::STATUS_PENDING ? 'Đang chờ duyệt đăng ký' : 'Cần đăng ký và được duyệt'];
    }

    /**
     * Nút chính + khối "Quyền tham gia" — giữ đúng thứ tự điều kiện của bản mẫu.
     * kind: disabled | register | enter | tab | link
     */
    private function action(Competition $c, bool $survey, string $period, bool $closed, bool $manager, ?User $viewer, bool $isStudent, ?string $regStatus, bool $submitted, array $access, bool $resume): array
    {
        $organizer = $this->organizerLabel($c);
        $action = [
            'kind' => 'disabled', 'label' => $access['label'], 'icon' => 'lock-keyhole',
            'title' => $period === 'upcoming' ? 'Kỳ thi chưa bắt đầu' : 'Kỳ thi đã kết thúc',
            'text' => $period === 'upcoming'
                ? 'Bạn có thể xem thông tin trước. Nút vào thi sẽ mở đúng thời gian tổ chức.'
                : 'Bạn vẫn có thể xem thông tin, bài đã nộp và kết quả của kỳ này.',
        ];

        if ($manager) {
            return ['kind' => 'tab', 'tab' => 'registrations', 'openAdd' => ! $closed, 'label' => $closed ? 'Danh sách học sinh' : 'Thêm học sinh', 'icon' => 'user-check',
                'title' => 'Không gian ban tổ chức', 'text' => 'Thêm học sinh theo lớp, duyệt đăng ký, theo dõi kết quả và in báo cáo cho phụ huynh.'];
        }
        if ($viewer === null) {
            return ['kind' => 'link', 'url' => route('login'), 'label' => 'Đăng nhập để tham gia', 'icon' => 'user-check',
                'title' => 'Tham gia cùng chúng tôi', 'text' => 'Sử dụng tài khoản học sinh để đăng ký cuộc thi hoặc nhập mật khẩu khảo sát.'];
        }
        if (! $isStudent) {
            return ['kind' => 'disabled', 'label' => 'Dành cho tài khoản học sinh', 'icon' => 'user-check',
                'title' => 'Tài khoản không phải học sinh', 'text' => 'Đăng nhập bằng tài khoản học sinh để đăng ký hoặc làm bài.'];
        }
        if (! $survey && $submitted) {
            return ['kind' => 'tab', 'tab' => 'results', 'label' => 'Xem kết quả', 'icon' => 'trophy',
                'title' => 'Bạn đã hoàn thành kỳ thi', 'text' => 'Bài nộp đã được ghi nhận. Kỳ thi này chỉ có một lượt nộp.'];
        }
        if (! $survey && ! $closed && ! in_array($regStatus, [CompetitionRegistration::STATUS_APPROVED, CompetitionRegistration::STATUS_PENDING], true)) {
            $rejected = $regStatus === CompetitionRegistration::STATUS_REJECTED;

            return ['kind' => 'register', 'url' => route('student.competitions.requestJoin', $c->id), 'label' => $rejected ? 'Gửi lại đăng ký' : 'Đăng ký tham gia', 'icon' => 'user-check',
                'title' => $rejected ? 'Đăng ký chưa được duyệt' : 'Đăng ký để sẵn sàng vào thi',
                'text' => 'Đăng ký một lần cho toàn cuộc thi. Giáo viên hoặc admin tổ chức sẽ xét duyệt trước khi bạn được làm bài.'];
        }
        if (! $survey && ! $closed && $regStatus === CompetitionRegistration::STATUS_PENDING) {
            return ['kind' => 'disabled', 'label' => 'Đang chờ xét duyệt', 'icon' => 'clock-3',
                'title' => 'Đăng ký đang chờ duyệt', 'text' => $organizer.' hoặc admin tổ chức sẽ xác nhận quyền tham gia của bạn.'];
        }
        if ($access['allowed']) {
            return ['kind' => 'enter', 'label' => $resume ? 'Tiếp tục làm bài' : $access['label'], 'icon' => $survey ? 'key-round' : 'play',
                'title' => $resume ? 'Bài làm đang chờ bạn' : ($survey ? ($submitted ? 'Thử lại để tiến bộ hơn' : 'Khám phá năng lực của bạn') : 'Bạn đã được duyệt!'),
                'text' => $resume
                    ? 'Bài làm đang dở được lưu trên máy chủ. Bạn có thể tiếp tục trước khi kỳ thi đóng.'
                    : ($survey ? 'Nhập mật khẩu giáo viên cung cấp. Mỗi lượt làm được lưu riêng để theo dõi tiến bộ.' : 'Kỳ thi đang mở. Bạn có một lượt nộp để ghi nhận điểm và thứ hạng.')];
        }
        if ($survey && $submitted && $period === 'completed') {
            return ['kind' => 'tab', 'tab' => 'results', 'label' => 'Xem kết quả', 'icon' => 'bar-chart-3',
                'title' => 'Khảo sát đã kết thúc', 'text' => 'Xem lại điểm số và tiến bộ của bạn qua các lượt làm.'];
        }
        if ($survey && $period === 'current' && ! $access['allowed']) {
            return ['kind' => 'disabled', 'label' => $access['label'], 'icon' => 'lock-keyhole',
                'title' => 'Đề khảo sát đang được chuẩn bị', 'text' => 'Giáo viên sẽ chốt một bộ câu hỏi chung trước khi học sinh bắt đầu.'];
        }

        return $action;
    }

    private function noRoundAction(bool $manager): array
    {
        return ['kind' => 'disabled', 'label' => 'Chưa có kỳ thi', 'icon' => 'lock-keyhole',
            'title' => 'Ban tổ chức đang chuẩn bị', 'text' => $manager ? 'Thêm kỳ thi (vòng) ở trang quản trị cuộc thi.' : 'Các kỳ thi sẽ hiện ở đây khi ban tổ chức công bố lịch.'];
    }

    // ───────────────────────────── Bảng xếp hạng ─────────────────────────────

    /**
     * @param  array<int, array{id:int, points:float}>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function rankings(CompetitionExam $exam, array $questions, float $totalPoints, bool $survey, ?User $viewer, bool $manager, int $formCount): array
    {
        $attempts = Attempt::query()
            ->where('competition_exam_id', $exam->id)
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at')->orderBy('id')
            ->limit(self::RANKING_ATTEMPT_LIMIT)
            ->get(['id', 'user_id', 'status', 'total_score', 'submitted_at', 'is_provisional']);

        $graded = fn (Attempt $a) => $a->total_score !== null && $a->status === AttemptStatus::Graded;

        $rows = [];
        foreach ($attempts->groupBy('user_id') as $userId => $list) {
            $ok = $list->filter($graded)->values();
            if ($ok->isEmpty()) {
                continue;
            }
            // Khảo sát: lượt điểm cao nhất (bằng điểm → lượt sớm hơn). Cuộc thi: lượt nộp đầu tiên (duy nhất).
            $best = $survey ? $ok->sort(fn ($a, $b) => [(float) $b->total_score, $a->submitted_at] <=> [(float) $a->total_score, $b->submitted_at])->first() : $ok->first();
            $last = $list->sortBy([['submitted_at', 'desc'], ['id', 'desc']])->first();

            $rows[] = [
                'userId' => (int) $userId,
                'attemptId' => (int) $best->id,
                'score' => (float) $best->total_score,
                'at' => $best->submitted_at,
                'attemptCount' => $list->count(),
                'gradedCount' => $ok->count(),
                'latestScore' => $graded($last) ? (float) $last->total_score : null,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['score'], $a['at'], $a['userId']] <=> [$a['score'], $b['at'], $b['userId']]);

        if ($rows === []) {
            return [];
        }

        $userIds = array_column($rows, 'userId');
        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id')->all();
        $classes = $manager ? $this->organizers->classNamesFor($userIds) : [];

        $perQuestion = [];
        if ($questions !== []) {
            AttemptAnswer::query()
                ->whereIn('attempt_id', array_column($rows, 'attemptId'))
                ->get(['attempt_id', 'question_id', 'score', 'graded_at'])
                ->each(function ($ans) use (&$perQuestion) {
                    if ($ans->score !== null) {
                        $perQuestion[(int) $ans->attempt_id][(int) $ans->question_id] = (float) $ans->score;
                    }
                });
        }

        $formsByUser = [];
        if (! $survey && $formCount > 1 && CompetitionRegistration::supportsHubFields()) {
            CompetitionRegistration::query()->where('competition_id', $exam->competition_id)->whereIn('student_id', $userIds)
                ->get(['student_id', 'exam_forms'])
                ->each(function ($r) use (&$formsByUser, $exam) {
                    $no = (int) (((array) $r->exam_forms)[(string) $exam->id] ?? 0);
                    if ($no > 0) {
                        $formsByUser[(int) $r->student_id] = ContestForms::label($no);
                    }
                });
        }

        $out = [];
        foreach ($rows as $i => $r) {
            $isViewer = $viewer !== null && $r['userId'] === (int) $viewer->id;
            $out[] = [
                'rank' => $i + 1,
                'userId' => $r['userId'],
                // Ẩn danh với người ngoài ban tổ chức (giữ đúng chính sách cũ — đa số học sinh dưới 18 tuổi).
                'name' => ($manager || $isViewer) ? ($names[$r['userId']] ?? 'Học sinh') : 'Học viên đã xác thực',
                'className' => $classes[$r['userId']] ?? null,
                'isViewer' => $isViewer,
                'score' => $r['score'],
                'scoreLabel' => $this->num($r['score']).'/'.$this->num($totalPoints),
                'normalized' => $totalPoints > 0 ? round($r['score'] / $totalPoints * 100, 2) : 0.0,
                'latestLabel' => $r['latestScore'] === null ? 'Chờ chấm' : $this->num($r['latestScore']).'/'.$this->num($totalPoints),
                'attemptCount' => $r['attemptCount'],
                'gradedCount' => $r['gradedCount'],
                'atLabel' => $r['at']?->format('H:i d/m/Y') ?? '—',
                'formLabel' => $formsByUser[$r['userId']] ?? null,
                'perQuestion' => array_map(fn ($q) => $perQuestion[$r['attemptId']][$q['id']] ?? null, $questions),
            ];
        }

        return $out;
    }

    /** Phân bố điểm (thang 100) — bản mẫu: ScoreDistribution. */
    private function distribution(array $rankings): array
    {
        $bands = [['0–24', 0, 25], ['25–49', 25, 50], ['50–74', 50, 75], ['75–99', 75, 100], ['100', 100, 101]];
        $groups = array_map(fn ($b) => ['label' => $b[0], 'count' => count(array_filter($rankings, fn ($r) => $r['normalized'] >= $b[1] && $r['normalized'] < $b[2]))], $bands);
        $max = max(1, ...array_column($groups, 'count'));

        return array_map(fn ($g) => $g + ['height' => round($g['count'] / $max * 100, 1)], $groups);
    }

    /** Thống kê theo câu — bản mẫu: contestQuestionStats(). */
    private function questionStats(array $questions, array $rows): array
    {
        $stats = [];
        foreach ($questions as $i => $q) {
            $scores = array_values(array_filter(array_map(fn ($r) => $r['perQuestion'][$i] ?? null, $rows), fn ($s) => $s !== null));
            $count = count($scores);
            $correct = count(array_filter($scores, fn ($s) => $s >= $q['points'] - 1e-9));
            $stats[] = [
                'no' => $i + 1,
                'title' => $q['title'],
                'count' => $count,
                'total' => count($rows),
                'average' => $count ? $this->num(round(array_sum($scores) / $count, 1)).'/'.$this->num($q['points']) : '—',
                'rate' => $count ? round($correct / $count * 100).'% ('.$correct.'/'.$count.')' : '—',
            ];
        }

        return $stats;
    }

    // ───────────────────────────── Kết quả khảo sát của tôi ─────────────────────────────

    private function surveyResult(array $attemptRows, array $questions, Collection $attempts): array
    {
        $scores = array_values(array_filter(array_column($attemptRows, 'normalized'), fn ($s) => $s !== null));
        $latest = $attemptRows === [] ? null : end($attemptRows)['normalized'];

        // Đồ thị tiến bộ: khung 560×170 giống SurveyTrend, co giãn theo bề ngang bằng viewBox.
        $n = count($attemptRows);
        $right = 534;
        $points = [];
        foreach ($attemptRows as $i => $row) {
            $x = 32 + $i * ($right - 32) / max(1, $n - 1);
            $points[] = ['x' => round($n === 1 ? ($right + 32) / 2 : $x, 1), 'y' => round(136 - ($row['normalized'] ?? 0) * 1.08, 1), 'score' => $row['normalized'], 'label' => 'L'.($i + 1)];
        }

        // Thống kê theo câu qua các lượt của chính mình.
        $mine = [];
        if ($questions !== [] && $attempts->isNotEmpty()) {
            $byAttempt = [];
            AttemptAnswer::query()->whereIn('attempt_id', $attempts->pluck('id')->all())->get(['attempt_id', 'question_id', 'score'])
                ->each(function ($a) use (&$byAttempt) {
                    if ($a->score !== null) {
                        $byAttempt[(int) $a->attempt_id][(int) $a->question_id] = (float) $a->score;
                    }
                });
            $rows = array_map(fn ($a) => ['perQuestion' => array_map(fn ($q) => $byAttempt[(int) $a->id][$q['id']] ?? null, $questions)], $attempts->all());
            $mine = $this->questionStats($questions, $rows);
        }

        return [
            'count' => $n,
            'best' => $scores === [] ? '—' : $this->num(max($scores)),
            'latest' => $latest === null ? '—' : $this->num($latest),
            'points' => $points,
            'polyline' => implode(' ', array_map(fn ($p) => $p['x'].','.$p['y'], array_filter($points, fn ($p) => $p['score'] !== null))),
            'questionStats' => $mine,
        ];
    }

    // ───────────────────────────── Tab "Học sinh" ─────────────────────────────

    private function participants(Competition $c, CompetitionExam $exam, array $rankings, User $viewer): array
    {
        $regs = CompetitionRegistration::supported()
            ? CompetitionRegistration::query()->where('competition_id', $c->id)
                ->where('status', '!=', CompetitionRegistration::STATUS_WITHDRAWN)
                ->with(array_merge(['student:id,name,email,phone'], CompetitionRegistration::supportsHubFields() ? ['addedBy:id,name'] : []))
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('requested_at')
                ->get()
            : collect();

        $submittedIds = Attempt::query()->where('competition_exam_id', $exam->id)->whereNotNull('submitted_at')
            ->distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $byUser = [];
        foreach ($regs as $r) {
            $byUser[(int) $r->student_id] = [
                'registrationId' => (int) $r->id,
                'userId' => (int) $r->student_id,
                'name' => $r->student?->name ?? 'Tài khoản đã xoá',
                'account' => (string) ($r->student?->email ?: $r->student?->phone ?: ''),
                'status' => $r->status,
                'source' => CompetitionRegistration::supportsHubFields() ? $r->source : null,
                'addedBy' => CompetitionRegistration::supportsHubFields() ? $r->addedBy?->name : null,
                'rejectReason' => $r->reject_reason,
            ];
        }

        // Người đã có bài nộp ở kỳ này nhưng không có dòng đăng ký (dữ liệu cũ).
        $missing = array_diff($submittedIds, array_keys($byUser));
        if ($missing !== []) {
            User::query()->whereIn('id', $missing)->get(['id', 'name', 'email', 'phone'])->each(function ($u) use (&$byUser) {
                $byUser[(int) $u->id] = ['registrationId' => null, 'userId' => (int) $u->id, 'name' => $u->name, 'account' => (string) ($u->email ?: $u->phone ?: ''),
                    'status' => 'participant', 'source' => null, 'addedBy' => null, 'rejectReason' => null];
            });
        }

        $classes = $this->organizers->classNamesFor(array_keys($byUser));
        $resultByUser = [];
        foreach ($rankings as $row) {
            $resultByUser[$row['userId']] = $row;
        }

        $survey = $c->isSurvey();
        $rows = [];
        foreach ($byUser as $uid => $p) {
            $result = $resultByUser[$uid] ?? null;
            $rows[] = $p + [
                'className' => $classes[$uid] ?? '',
                'statusLabel' => match ($p['status']) {
                    CompetitionRegistration::STATUS_APPROVED => $survey ? 'Trong danh sách' : 'Đã duyệt',
                    CompetitionRegistration::STATUS_PENDING => 'Chờ duyệt',
                    CompetitionRegistration::STATUS_REJECTED => 'Đã từ chối',
                    default => 'Đã tham gia',
                },
                'tone' => in_array($p['status'], [CompetitionRegistration::STATUS_APPROVED, 'participant'], true) ? 'current' : 'completed',
                'resultLabel' => $result !== null ? $result['scoreLabel'] : (in_array($uid, $submittedIds, true) ? 'Chờ chấm' : 'Chưa nộp'),
                'resultNote' => $result !== null ? ($survey ? 'Cao nhất · '.$result['attemptCount'].' lượt' : 'Đã có điểm') : null,
                'addedLabel' => match ($p['source']) {
                    CompetitionRegistration::SOURCE_ORGANIZER => $p['addedBy'] ?: 'Ban tổ chức',
                    CompetitionRegistration::SOURCE_PASSWORD => 'Tự vào bằng mật khẩu',
                    default => $p['status'] === 'participant' ? '—' : 'Học sinh tự đăng ký',
                },
                'search' => Str::lower(Str::ascii(($p['name'] ?? '').' '.($classes[$uid] ?? '').' '.$p['account'])),
            ];
        }

        return [
            'rows' => $rows,
            'pending' => count(array_filter($rows, fn ($r) => $r['status'] === CompetitionRegistration::STATUS_PENDING)),
            'submitted' => count(array_filter($rows, fn ($r) => in_array($r['userId'], $submittedIds, true))),
            'searchUrl' => route('competitions.manage.students', $c->id),
            'addUrl' => route('competitions.manage.add', $c->id),
            'classes' => $this->organizers->classOptions($viewer),
        ];
    }

    // ───────────────────────────── Tiện ích ─────────────────────────────

    private function attemptRow(Attempt $a, int $i, float $totalPoints): array
    {
        $graded = $a->total_score !== null && $a->status === AttemptStatus::Graded;

        return [
            'id' => (int) $a->id,
            'no' => $i + 1,
            'at' => $a->submitted_at?->format('H:i d/m/Y') ?? '—',
            'scoreLabel' => $graded ? $this->num((float) $a->total_score).'/'.$this->num($totalPoints) : 'Chờ chấm',
            'normalized' => $graded && $totalPoints > 0 ? round((float) $a->total_score / $totalPoints * 100, 2) : null,
            'graded' => $graded,
            'resultUrl' => route('student.assessment.result', $a->id),
        ];
    }

    /** @return array<int, array{id:int, title:string, kind:string, points:float}> câu theo thứ tự GỐC của đề */
    private function questionsOf(CompetitionExam $exam): array
    {
        $assessment = $exam->assessment;
        if ($assessment === null) {
            return [];
        }

        if ($assessment->isPdfMode()) {
            return $assessment->answerKeys()->orderBy('question_no')->get()->values()->map(fn ($k, $i) => [
                'id' => 0, 'title' => 'Câu '.($i + 1), 'kind' => 'Phiếu trả lời', 'points' => (float) ($k->points ?? 0),
            ])->all();
        }

        return $assessment->items()->with('question:id,title,type,points')->orderBy('order')->get()->values()
            ->map(fn ($item, $i) => [
                'id' => (int) $item->question_id,
                'title' => $item->question?->title ?: 'Câu '.($i + 1),
                'kind' => match ($item->question?->type?->value) {
                    'coding' => 'Lập trình',
                    'fill_blank' => 'Điền đáp án',
                    'composite' => 'Nhiều phần',
                    default => 'Trắc nghiệm',
                },
                'points' => (float) $item->effectivePoints(),
            ])->all();
    }

    private function pickRound(Collection $exams, ?int $roundId): ?CompetitionExam
    {
        if ($exams->isEmpty()) {
            return null;
        }
        if ($roundId !== null && ($found = $exams->firstWhere('id', $roundId)) !== null) {
            return $found;
        }

        return $exams->first(fn ($e) => $this->roundState($e) === 'current')
            ?? $exams->first(fn ($e) => $this->roundState($e) === 'upcoming')
            ?? $exams->last();
    }

    public function roundState(CompetitionExam $exam): string
    {
        return match ($exam->computedStatus()) {
            'upcoming' => 'upcoming',
            'ended' => 'completed',
            default => 'current',
        };
    }

    /** Trạng thái cả sự kiện suy từ các kỳ thi (bản mẫu: eventState). */
    public function eventState(Competition $c): string
    {
        if ($c->status === CompetitionStatus::Archived) {
            return 'completed';
        }

        // SỬA 24/10 — cuộc thi có đặt Bắt đầu / Kết thúc riêng thì trạng thái chung theo đúng 2 mốc đó.
        if ($c->starts_at !== null && now()->lt($c->starts_at)) {
            return 'upcoming';
        }
        if ($c->ends_at !== null && now()->gt($c->ends_at)) {
            return 'completed';
        }

        $states = $c->examSittings->map(fn ($e) => $this->roundState($e))->all();
        if ($states === []) {
            return match ($c->computedStatus()) {
                CompetitionStatus::Ongoing => 'current',
                CompetitionStatus::Upcoming => 'upcoming',
                default => 'completed',
            };
        }

        return in_array('current', $states, true) ? 'current' : (in_array('upcoming', $states, true) ? 'upcoming' : 'completed');
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} ngày bắt đầu sớm nhất / kết thúc muộn nhất của các kỳ thi */
    private function eventRange(Competition $c): array
    {
        // SỬA 24/10 — ưu tiên Bắt đầu / Kết thúc admin nhập cho cuộc thi; để trống mới lấy theo các kỳ thi.
        $start = $c->starts_at ?? $c->examSittings->pluck('starts_at')->filter()->sort()->first();
        $end = $c->ends_at ?? $c->examSittings->pluck('ends_at')->filter()->sort()->last();

        return [$start, $end];
    }

    /**
     * SỬA 24/10 (khách: "trạng thái không khớp bắt đầu / kết thúc") — nhãn trạng thái ghi thêm còn bao lâu để đối chiếu
     * được với ngày giờ: "Sắp diễn ra · còn 31 ngày", "Đang diễn ra · còn 5 giờ".
     */
    private function stateHint(string $state, ?Carbon $start, ?Carbon $end): string
    {
        $label = self::STATE_LABELS[$state];
        $target = match ($state) {
            'upcoming' => $start,
            'current' => $end,
            default => null,
        };
        if ($target === null || now()->gte($target)) {
            return $label;
        }

        $minutes = (int) now()->diffInMinutes($target, true);
        $left = match (true) {
            $minutes >= 1440 => (int) ceil($minutes / 1440).' ngày',
            $minutes >= 60 => (int) floor($minutes / 60).' giờ',
            default => max(1, $minutes).' phút',
        };

        return $label.' · còn '.$left;
    }

    private function durationLabel(Collection $rounds): string
    {
        $minutes = $rounds->map(fn ($e) => $e->assessment?->duration_minutes)->filter()->unique()->sort()->values();

        return match ($minutes->count()) {
            0 => 'Theo đề',
            1 => $minutes->first().' phút',
            default => $minutes->first().'–'.$minutes->last().' phút',
        };
    }

    private function roadmap(Competition $c): ?array
    {
        // SỬA 24/10 — "Lộ trình học" = KHOÁ HỌC của cuộc thi (competitions.course_id), link sang trang khoá học.
        if (! Competition::supportsCourse() || $c->course_id === null || ! $c->relationLoaded('course') || $c->course === null) {
            return null;
        }

        return ['id' => (int) $c->course->id, 'title' => $c->course->title, 'href' => route('courses.show', $c->course->id)];
    }

    private function description(Competition $c): string
    {
        $text = Competition::supportsHubFields() ? trim((string) $c->description) : '';
        if ($text === '') {
            $text = Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $c->rules)), 220, '…');
        }

        return $text !== '' ? $text : ($c->isSurvey()
            ? 'Hiểu năng lực hiện tại, xác định kiến thức cần củng cố và theo dõi tiến bộ qua từng lượt làm bài.'
            : 'Thử sức với các kỳ thi, rèn tư duy giải quyết vấn đề và tranh tài cùng các bạn.');
    }

    private function editionLabel(Competition $c): string
    {
        $label = Competition::supportsHubFields() ? trim((string) $c->edition_label) : '';
        if ($label !== '') {
            return $label;
        }
        [$start] = $this->eventRange($c);

        return $start !== null ? 'Mùa '.$start->format('Y') : '';
    }

    public function organizerLabel(Competition $c): string
    {
        if ($c->isExternallyOrganized() && trim((string) $c->organizer_name) !== '') {
            return trim((string) $c->organizer_name);
        }

        $names = $c->advisors->pluck('name')->filter()->take(2)->all();

        return $names !== [] ? implode(', ', $names) : 'Ban tổ chức Ôn Thi 360';
    }

    private function num(float $v): string
    {
        $t = number_format($v, 2, '.', '');

        return str_contains($t, '.') ? rtrim(rtrim($t, '0'), '.') : $t;
    }
}

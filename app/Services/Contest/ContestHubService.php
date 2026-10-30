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
        // SỬA 24/10 — đề sinh từ ma trận (nhiều đề / kỳ).
        private readonly ContestPaperService $papers,
    ) {}

    // ───────────────────────────── Danh sách ─────────────────────────────

    public function catalogData(?User $viewer, ?string $type = null): array
    {
        $hub = Competition::supportsHubFields();

        $competitions = Competition::query()
            ->where('status', '!=', CompetitionStatus::Archived->value)
            ->with(array_merge(['examSittings.assessment:id,title,duration_minutes', 'advisors:id,name'], Competition::supportsCourse() ? ['course:id,title,status'] : []))
            ->latest('id')
            ->limit(200)
            ->get();

        // SỬA 25/10 (khách: "cho thấy hết, chưa có kỳ vẫn hiển thị") — học sinh / khách thấy mọi sự kiện như ban tổ chức
        // (bỏ lọc "chưa có kỳ / khoá học chưa phát hành"). Sự kiện chưa có kỳ: nhãn "Sắp mở · Chưa có kỳ" (xem card()).

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
            'title' => $c->displayTitle(),
            'edition' => $this->editionLabel($c),
            'isSurvey' => $survey,
            'type' => $survey ? 'survey' : 'contest',
            'url' => route('competitions.show', $c->id),
            'image' => $c->imageUrl(),
            // SỬA 25/10 — chưa có kỳ nào (và không đặt lịch riêng): hiện "Sắp mở", không suy ra "Đã kết thúc".
            'state' => $rounds->isEmpty() && $start === null && $end === null ? 'upcoming' : $state,
            'stateLabel' => $rounds->isEmpty() && $start === null && $end === null ? 'Sắp mở · Chưa có kỳ' : $this->stateHint($state, $start, $end),
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

    /**
     * SỬA 25/10 (khách: "UI phòng thi như source mới, cả admin lẫn giáo viên") — 2 kiểu xem:
     *   · Ban tổ chức (admin / giáo viên — canCreateRound): "Các kỳ tôi tổ chức" — giáo viên chỉ thấy kỳ MÌNH chủ trì
     *     (admin thấy mọi kỳ), nút "Thêm kỳ thi" / "Thêm kỳ khảo sát", tab Tổng quan · Thiết lập đề (hoặc Lấy đề có sẵn)
     *     · Học sinh · Bảng xếp hạng. Bản mẫu: ContestRoomPage.jsx + ContestOrganizerOverview.jsx + ContestPaperSettings.jsx.
     *   · Học sinh / khách: như trước, thêm dòng "Chủ trì" ở từng kỳ.
     */
    public function roomData(int $competitionId, ?User $viewer, ?int $roundId = null, ?string $tab = null): array
    {
        /** @var Competition $c */
        $c = Competition::query()
            ->with(array_merge(
                ['examSittings.assessment', 'advisors:id,name'],
                CompetitionExam::supportsRoundSetup() ? ['examSittings.organizer:id,name'] : [],
                Competition::supportsCourse() ? ['course:id,title'] : [],
            ))
            ->findOrFail($competitionId);

        $survey = $c->isSurvey();
        $staff = $this->organizers->canCreateRound($viewer);
        $manager = $this->organizers->canManage($viewer, $c);
        $isStudent = $viewer !== null && $viewer->hasRole(Role::STUDENT) && ! $staff;
        $registration = $isStudent ? $this->entry->registrationFor($viewer, $c->id) : null;
        $eventState = $this->eventState($c);
        $closed = $eventState === 'completed';

        // Ban tổ chức chỉ thấy các kỳ mình quản lý được (admin: mọi kỳ).
        $exams = $c->examSittings
            ->filter(fn (CompetitionExam $e) => ! $staff || $this->organizers->canManageRound($viewer, $c, $e))
            ->values();
        $examIds = $exams->pluck('id')->map(fn ($id) => (int) $id)->all();

        $myAttempts = ($viewer !== null && $examIds !== [])
            ? Attempt::query()->where('user_id', $viewer->id)->whereIn('competition_exam_id', $examIds)->orderBy('id')->get()->groupBy('competition_exam_id')
            : collect();

        $rounds = $exams->map(function (CompetitionExam $e, int $i) use ($myAttempts, $c) {
            $mine = $myAttempts->get($e->id, collect());
            $state = $this->roundState($e);

            return [
                'id' => (int) $e->id,
                'no' => $i + 1,
                'noLabel' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'label' => $e->displayTitle(),
                'host' => $this->roundHost($c, $e),
                'state' => $state,
                'stateLabel' => self::STATE_LABELS[$state],
                'dateRange' => ($e->starts_at?->format('d/m/Y') ?? 'Chưa xếp lịch').($e->ends_at ? ' – '.$e->ends_at->format('d/m/Y') : ''),
                'submitted' => $mine->contains(fn ($a) => $a->submitted_at !== null),
                'hasDraft' => $mine->contains(fn ($a) => $a->status === AttemptStatus::InProgress),
                'url' => route('competitions.show', ['competition' => $e->competition_id, 'vong' => $e->id]),
            ];
        })->all();

        $selectedExam = $this->pickRound($exams, $roundId);
        $doneCount = count(array_filter($rounds, fn ($r) => $r['submitted']));
        $isAdmin = $viewer !== null && $this->organizers->isAdmin($viewer);
        $kind = $survey ? 'kỳ khảo sát' : 'kỳ thi';

        $base = [
            'competition' => $c,
            'contest' => [
                'id' => $c->id,
                'title' => $c->displayTitle(),
                'isSurvey' => $survey,
                'image' => $c->imageUrl(),
                'editionLabel' => $this->editionLabel($c),
                'description' => $this->description($c),
                'rules' => trim((string) $c->rules),
                'organizer' => $this->organizerLabel($c),
                'roadmap' => $this->roadmap($c),
                'state' => $eventState,
                'stateLabel' => $this->stateHint($eventState, ...$this->eventRange($c)),
                'publishLabel' => $c->publish_result_at?->format('H:i d/m/Y'),
                'kind' => $kind,
            ],
            'viewer' => [
                'loggedIn' => $viewer !== null,
                'isStudent' => $isStudent,
                'isStaff' => $staff,
                'isManager' => $manager,
                'isAdmin' => $isAdmin,
                'registrationStatus' => $registration?->status,
                'name' => $viewer?->name,
                'roleLabel' => match (true) {
                    $staff => 'Người tổ chức',
                    $isStudent => 'Học sinh',
                    $viewer !== null => 'Chế độ xem',
                    default => 'Khách · Chế độ xem',
                },
            ],
            'rounds' => $rounds,
            'roundsHeading' => $staff ? ($isAdmin ? 'Các kỳ đang tổ chức' : 'Các kỳ tôi tổ chức') : ($survey ? 'Các kỳ khảo sát' : 'Các kỳ thi'),
            'doneCount' => $doneCount,
            'closed' => $closed,
            'loginUrl' => route('login'),
            'catalogUrl' => route('competitions.index'),
            'adminUrl' => $isAdmin ? route('admin.competitions.show', $c->id) : null,
            'serverNowMs' => (int) round(microtime(true) * 1000),
            'roundEditor' => $staff ? $this->organizers->roundEditor($c, $viewer) : null,
        ];

        if ($selectedExam === null) {
            return $base + ['round' => null, 'tabs' => [], 'activeTab' => 'overview', 'action' => $this->noRoundAction($staff, $kind)];
        }

        $roundManager = $staff && $this->organizers->canManageRound($viewer, $c, $selectedExam);

        return $base + $this->roundData($c, $selectedExam, $viewer, $registration, $roundManager, $isStudent, $closed, $myAttempts->get($selectedExam->id, collect()), $tab);
    }

    private function roundData(
        Competition $c,
        CompetitionExam $exam,
        ?User $viewer,
        ?CompetitionRegistration $registration,
        bool $manager,
        bool $isStudent,
        bool $closed,
        Collection $mine,
        ?string $tab,
    ): array {
        $survey = $c->isSurvey();
        $source = $exam->sourceType($c);
        // SỬA 25/10 — nguồn "đề có sẵn" dùng đề gắn ở kỳ; nguồn "câu hỏi" dùng các đề ma trận đã chốt.
        $papers = $source === CompetitionExam::SOURCE_QUESTIONS ? $this->papers->publishedPapers($exam) : collect();
        // Học sinh tự chọn đề: chọn sẵn đề làm lần trước, chưa có thì đề đầu tiên. Khảo sát thoát giữa chừng là làm lại
        // từ đầu (ContestEntryService::enter() huỷ lượt dở) nên không khoá theo đề đang làm dở.
        $myPaper = ($isStudent && $papers->isNotEmpty()) ? $this->papers->paperFor($viewer, $exam) : null;
        // SỬA 25/10 — kỳ chọn nhiều đề từ ngân hàng: học sinh được bốc ngẫu nhiên 1 đề (ContestPaperService::bankAssessmentFor()).
        $bankIds = $source === CompetitionExam::SOURCE_EXAM ? $exam->bankAssessmentIds() : [];
        $bank = count($bankIds) > 1
            ? \App\Models\Assessment::query()->whereIn('id', $bankIds)->get()->sortBy(fn ($a) => array_search((int) $a->id, $bankIds, true))->values()
            : collect();
        $myBankId = ($isStudent && $bank->isNotEmpty()) ? $this->papers->bankAssessmentFor($viewer, $exam) : null;
        $assessment = match (true) {
            $papers->isNotEmpty() => ($myPaper ?? $papers->first())->assessment,
            $bank->isNotEmpty() => $bank->firstWhere('id', $myBankId) ?? $bank->first(),
            $source === CompetitionExam::SOURCE_EXAM => $exam->assessment,
            default => null,
        };
        $period = $this->roundState($exam);
        $questions = $this->questionsOfAssessment($assessment);
        // Bảng xếp hạng / thống kê theo câu: kỳ nhiều đề thì gộp câu của mọi đề.
        $rankQuestions = match (true) {
            $papers->count() > 1 => $this->questionsOf($exam, $papers),
            $bank->isNotEmpty() => $this->questionsOf($exam, $bank->map(fn ($a) => (object) ['assessment' => $a])),
            default => $questions,
        };
        $totalPoints = (float) ($assessment?->total_points ?: array_sum(array_column($questions, 'points')) ?: 100);
        $duration = $assessment?->duration_minutes ? $assessment->duration_minutes.' phút' : 'Không giới hạn';

        // Bài nộp của tôi (kỳ đang xem)
        $attempts = $mine->filter(fn (Attempt $a) => $a->submitted_at !== null)->values();
        $attemptRows = $attempts->map(fn (Attempt $a, int $i) => $this->attemptRow($a, $i, $totalPoints))->all();
        $latest = $attemptRows === [] ? null : end($attemptRows);
        $inProgress = $mine->first(fn (Attempt $a) => $a->status === AttemptStatus::InProgress);

        // Mã đề hoán vị (cuộc thi)
        $isPdf = $assessment?->isPdfMode() ?? false;
        $formCount = (! $survey && ! $isPdf && $bank->isEmpty()) ? $exam->formCount() : 1; // nhiều đề: không hoán vị
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
        $rankings = $this->rankings($exam, $rankQuestions, $totalPoints, $survey, $viewer, $manager, $formCount);
        $myRank = 0;
        foreach ($rankings as $i => $row) {
            if ($row['isViewer']) {
                $myRank = $i + 1;
                break;
            }
        }

        $participants = ($manager && $viewer !== null) ? $this->participants($c, $exam, $rankings, $viewer) : null;
        $setup = $this->roundSetup($c, $exam, $source, $assessment, $papers, $questions, $formCount, $bank);

        // Khảo sát: lượt dở không "tiếp tục" được — bắt đầu lại là lượt mới (xem ContestEntryService::enter()).
        $resume = $inProgress !== null && ! $survey;
        $access = $this->access($survey, $period, $isStudent, $assessment !== null, $latest !== null, $registration?->status);
        $action = $manager
            ? $this->managerAction($survey, $period, $setup['ready'], (int) ($participants['pending'] ?? 0))
            : $this->action($c, $survey, $period, $closed, false, $viewer, $isStudent, $registration?->status, $latest !== null, $access, $resume);

        $rankTab = $survey ? 'ranking' : 'results';
        // SỬA 25/10 (khách: "khảo sát ẩn tab Học sinh, không cần add; chỉ cuộc thi mới cần") — khảo sát vào bằng mật khẩu.
        $tabs = $manager
            ? array_values(array_filter([
                ['id' => 'overview', 'icon' => 'list-checks', 'label' => 'Tổng quan', 'count' => null],
                ['id' => 'paper', 'icon' => 'file-text', 'label' => $setup['tabLabel'], 'count' => null],
                $survey ? null : ['id' => 'registrations', 'icon' => 'user-check', 'label' => 'Học sinh', 'count' => $participants !== null ? ($participants['pending'] ?: count($participants['rows'])) : null],
                ['id' => $rankTab, 'icon' => 'trophy', 'label' => 'Bảng xếp hạng', 'count' => null],
                // SỬA 30/10 (khách: "thêm tab Nhật ký — admin, giáo viên xem toàn bộ") — nhật ký làm đề của mọi học sinh.
                ['id' => 'activity', 'icon' => 'history', 'label' => 'Nhật ký', 'count' => null],
            ]))
            : array_values(array_filter([
                ['id' => 'overview', 'icon' => 'list-checks', 'label' => $survey ? 'Thông tin kỳ khảo sát' : 'Thông tin kỳ thi', 'count' => null],
                $viewer !== null ? ['id' => 'submissions', 'icon' => 'file-text', 'label' => 'Bài nộp của tôi', 'count' => count($attemptRows)] : null,
                ['id' => 'results', 'icon' => $survey ? 'bar-chart-3' : 'trophy', 'label' => $survey ? 'Kết quả năng lực' : 'Bảng xếp hạng', 'count' => null],
                $survey ? ['id' => 'ranking', 'icon' => 'trophy', 'label' => 'Bảng xếp hạng', 'count' => null] : null,
                // SỬA 30/10 — học sinh (hoặc ai đã có lượt làm ở kỳ này): CHỈ xem nhật ký của chính mình.
                ($viewer !== null && ($isStudent || $mine->isNotEmpty())) ? ['id' => 'activity', 'icon' => 'history', 'label' => 'Nhật ký của tôi', 'count' => null] : null,
            ]));
        $tabIds = array_column($tabs, 'id');

        $countdownTarget = match ($period) {
            'upcoming' => $exam->starts_at,
            'current' => $exam->ends_at,
            default => null,
        };

        return [
            'round' => [
                'id' => (int) $exam->id,
                'label' => $exam->displayTitle(),
                'host' => $this->roundHost($c, $exam),
                'state' => $period,
                'stateLabel' => self::STATE_LABELS[$period],
                'timeFrom' => $exam->starts_at?->format('H:i d/m/Y') ?? 'Chưa xếp lịch',
                'timeTo' => $exam->ends_at?->format('H:i d/m/Y') ?? 'Không giới hạn',
                'countdownLabel' => $period === 'upcoming' ? 'Mở kỳ thi sau' : ($period === 'current' ? 'Đóng kỳ thi sau' : 'Đã đóng kỳ thi'),
                'countdownTargetMs' => $countdownTarget !== null ? $countdownTarget->getTimestamp() * 1000 : null,
                'deadline' => $this->deadlineText($period, $countdownTarget),
                'duration' => $duration,
                'questionCount' => count($questions),
                'totalPoints' => $this->num($totalPoints),
                'hasAssessment' => $assessment !== null,
                'isPdf' => $isPdf,
                'assignmentMode' => $exam->assignmentMode(),
                'formCount' => $formCount,
                'enterUrl' => route('student.competitions.enter', ['competition' => $c->id, 'exam' => $exam->id]),
                // SỬA 30/10 — dữ liệu tab Nhật ký (JSON, tải khi mở tab).
                'activityUrl' => $viewer !== null ? route('competitions.activity', ['competition' => $c->id, 'exam' => $exam->id]) : null,
                'needsPassword' => $survey && $c->hasEntryPassword(),
                'paperCount' => $papers->count(),
                'bankCount' => $bank->count(), // SỬA 25/10 — kỳ nhiều đề ngân hàng
                'myPaperLabel' => $myPaper?->label,
                'paperStateUrl' => ($manager && $survey && $source === CompetitionExam::SOURCE_QUESTIONS && \App\Models\CompetitionExamPaper::supported())
                    ? route('competitions.manage.papers', ['competition' => $c->id, 'exam' => $exam->id]) : null,
            ],
            'setup' => $setup,
            // SỬA 30/10 (khách: "admin, giáo viên làm thử đề lúc nào cũng được để kiểm tra chấm") — đề làm thử được của kỳ.
            'testRun' => $manager ? [
                'url' => route('competitions.manage.test', ['competition' => $c->id, 'exam' => $exam->id]),
                'options' => app(\App\Http\Controllers\ContestTestRunController::class)->testableAssessments($exam),
            ] : null,
            'organizerOverview' => $participants !== null ? [
                // SỬA 30/10 (khách: "kỳ khảo sát thứ 2 chưa ai tham gia mà vẫn hiện 7 như kỳ đầu") — KHẢO SÁT: chỉ tính học sinh đã
                // nhập mật khẩu VÀO LÀM ĐÚNG KỲ NÀY (có lượt làm bài ở kỳ này: đang làm hoặc đã nộp). Danh sách đăng ký là của cả
                // cuộc khảo sát (dùng chung mọi kỳ) nên không dùng để đếm. Cuộc thi: vẫn đếm học sinh trong danh sách như cũ.
                'participants' => $survey
                    ? Attempt::query()->where('competition_exam_id', $exam->id)->distinct()->count('user_id')
                    : count($participants['rows']),
                'pending' => $participants['pending'],
                'records' => $participants['records'],
                'submitters' => $participants['submitted'],
                'password' => $survey ? $c->entryPassword() : null,
            ] : null,
            'questions' => $questions,
            // Danh sách đề của kỳ (khảo sát nhiều đề) cho tab Thông tin kỳ thi.
            'paperList' => $papers->map(fn ($p) => [
                'id' => (int) $p->id,
                'label' => $p->label,
                'questionCount' => $p->assessment ? $p->assessment->items()->count() : 0,
                'duration' => $p->assessment?->duration_minutes,
                'questions' => ($manager || ($survey && $attemptRows !== [])) ? $this->questionsOfAssessment($p->assessment) : [],
            ])->values()->all(),
            'myPaperId' => $myPaper?->id,
            'surveyDraft' => $survey && $inProgress !== null,
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
            'questionStats' => $resultsPublic ? $this->questionStats($rankQuestions, $rankings) : [],
            'rankQuestions' => $rankQuestions,
            'surveyResult' => $survey ? $this->surveyResult($attemptRows, $questions, $attempts) : null,
            'participants' => $participants,
            'roundManager' => $manager,
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

    /**
     * SỬA 25/10 — tình trạng đề của kỳ cho ban tổ chức (ô "Bộ đề", tab Thiết lập đề / Lấy đề có sẵn, thẻ Tổng quan).
     * Bản mẫu: ContestPaperTab + hub-round-facts trong ContestRoomPage.jsx.
     */
    private function roundSetup(Competition $c, CompetitionExam $exam, string $source, ?\App\Models\Assessment $assessment, Collection $papers, array $questions, int $formCount, ?Collection $bank = null): array
    {
        $survey = $c->isSurvey();
        $fromBank = $source === CompetitionExam::SOURCE_EXAM;
        $bank ??= collect();

        if ($fromBank && $bank->isNotEmpty()) {
            // SỬA 25/10 — nhiều đề ngân hàng: mỗi học sinh được bốc ngẫu nhiên 1 đề.
            $count = $bank->count();
            $mode = 'random';
            $sizes = $bank->map(fn ($a) => $a->isPdfMode() ? $a->answerKeys()->count() : $a->items()->count())->values();
            $forms = $bank->values()->map(fn ($a, $i) => [
                'label' => 'Đề '.($i + 1),
                'title' => (string) $a->title,
                // SỬA 27/10 (khách: "thang điểm theo trong đề, không phải 100 mặc định") — tổng điểm thật của đề.
                'meta' => ($sizes[$i] ?? 0).' câu · '.$this->num((float) $a->total_points).' điểm',
            ])->all();
            $perForm = $sizes->min() === $sizes->max() ? (string) $sizes->min() : $sizes->min().'–'.$sizes->max();
        } elseif ($fromBank) {
            $count = $assessment !== null ? ($survey ? 1 : $formCount) : 0;
            $mode = $count <= 1 ? 'fixed' : $exam->assignmentMode();
            $forms = $assessment === null ? [] : array_map(fn (int $no) => [
                'label' => ContestForms::label($no),
                'title' => (string) $assessment->title,
                'meta' => count($questions).' câu · '.$this->num((float) ($assessment->total_points ?: array_sum(array_column($questions, 'points')))).' điểm', // SỬA 27/10 — điểm thật của đề
            ], range(1, $count));
            $perForm = (string) count($questions);
        } else {
            $count = $papers->count();
            $mode = $count <= 1 ? 'fixed' : 'choice'; // khảo sát nhiều đề: học sinh tự chọn đề (ContestPaperService::choosePaper)
            $sizes = $papers->map(fn ($p) => $p->assessment ? $p->assessment->items()->count() : 0)->values();
            $forms = $papers->values()->map(fn ($p, $i) => [
                'label' => $p->label,
                'title' => 'Đề ma trận '.($i + 1),
                'meta' => ($sizes[$i] ?? 0).' câu · 100 điểm',
            ])->all();
            $perForm = $sizes->isEmpty() ? '0' : ($sizes->min() === $sizes->max() ? (string) $sizes->min() : $sizes->min().'–'.$sizes->max());
        }

        if ($count === 0) {
            // Chưa có đề: hiện cách giao sẽ áp dụng (cuộc thi theo thiết lập kỳ; khảo sát nhiều đề → học sinh tự chọn).
            $mode = $fromBank && ! $survey ? $exam->assignmentMode() : 'choice';
        }
        $ready = $count > 0 && ($questions !== [] || ($assessment?->isPdfMode() ?? false));
        $modeLabel = ['fixed' => 'Đề cố định', 'choice' => 'Tự chọn', 'random' => 'Ngẫu nhiên'][$mode];
        $drafts = ! $fromBank && \App\Models\CompetitionExamPaper::supported()
            ? \App\Models\CompetitionExamPaper::query()->where('competition_exam_id', $exam->id)->count() - $count : 0;
        $locked = ! $survey && Attempt::query()->where('competition_exam_id', $exam->id)->exists();

        return [
            'source' => $source,
            'fromBank' => $fromBank,
            'sourceLabel' => $fromBank ? 'Nguồn đề có sẵn' : 'Nguồn ma trận câu hỏi',
            // Khách: "chọn từ nguồn câu hỏi thì chữ Thiết lập đề; lấy từ đề có sẵn thì chữ Lấy đề có sẵn".
            'tabLabel' => $fromBank ? 'Lấy đề có sẵn' : 'Thiết lập đề',
            'ready' => $ready,
            'count' => $count,
            'perForm' => $perForm,
            'mode' => $mode,
            'modeLabel' => $modeLabel,
            'deliveryLabel' => ['fixed' => 'Một đề cố định', 'choice' => 'Học sinh tự chọn', 'random' => 'Giao ngẫu nhiên'][$mode],
            'factValue' => $ready ? $count.' đề' : 'Đang chuẩn bị',
            'factNote' => $ready ? $perForm.' câu/đề · '.$modeLabel : 'Chờ người tổ chức áp dụng',
            'status' => match (true) {
                $locked => 'Đã có bài làm',
                $fromBank => $assessment !== null ? ($bank->count() > 1 ? 'Đã chọn '.$bank->count().' đề' : 'Đã chọn đề') : 'Chưa chọn đề',
                $count > 0 => 'Đã chốt '.$count.' đề',
                $drafts > 0 => 'Đã lưu ma trận nháp',
                default => 'Chưa thiết lập ma trận',
            },
            'examTitle' => $fromBank ? ($bank->count() > 1 ? $bank->count().' đề · Bốc ngẫu nhiên 1 đề cho mỗi học sinh' : $assessment?->title) : null,
            'forms' => $forms,
            'locked' => $locked,
            'openBuilder' => ! $fromBank && $count === 0,
        ];
    }

    /** Nút chính của ban tổ chức — bản mẫu: nhánh manager trong ContestRoomPage.jsx. */
    private function managerAction(bool $survey, string $period, bool $ready, int $pending): array
    {
        $rankTab = $survey ? 'ranking' : 'results';

        if ($period === 'completed') {
            return ['kind' => 'tab', 'tab' => $rankTab, 'label' => 'Xem kết quả', 'icon' => 'bar-chart-3',
                'title' => 'Kỳ đã kết thúc', 'text' => 'Xem bài nộp, đối chiếu kết quả và in báo cáo trong Bảng xếp hạng.'];
        }
        if (! $ready) {
            return ['kind' => 'tab', 'tab' => 'paper', 'label' => 'Hoàn tất thiết lập đề', 'icon' => 'file-text',
                'title' => 'Cần chuẩn bị đề trước khi học sinh vào làm', 'text' => 'Chọn bộ đề, kiểm tra cách giao đề rồi áp dụng cho kỳ này.'];
        }
        if (! $survey && $pending > 0) {
            return ['kind' => 'tab', 'tab' => 'registrations', 'label' => 'Duyệt đăng ký', 'icon' => 'user-check',
                'title' => $pending.' học sinh đang chờ duyệt', 'text' => 'Bộ đề đã sẵn sàng. Mở danh sách để xác nhận học sinh được tham gia.'];
        }

        if ($survey) {
            // SỬA 25/10 — khảo sát không quản lý danh sách học sinh: biết mật khẩu là vào làm.
            return ['kind' => 'tab', 'tab' => $rankTab, 'label' => 'Xem bảng xếp hạng', 'icon' => 'trophy',
                'title' => $period === 'upcoming' ? 'Đã chuẩn bị đề · Chờ đến giờ mở' : 'Bộ đề đã sẵn sàng',
                'text' => 'Học sinh chỉ cần nhập đúng mật khẩu là vào khảo sát. Theo dõi các lượt làm trong Bảng xếp hạng.'];
        }

        return ['kind' => 'tab', 'tab' => 'registrations', 'label' => 'Quản lý học sinh', 'icon' => 'user-check',
            'title' => $period === 'upcoming' ? 'Đã chuẩn bị đề · Chờ đến giờ mở' : 'Bộ đề đã sẵn sàng',
            'text' => $survey
                ? 'Học sinh trong danh sách dùng mật khẩu để vào khảo sát. Theo dõi các lượt làm trong danh sách và bảng xếp hạng.'
                : 'Học sinh được duyệt có thể vào thi khi kỳ mở. Theo dõi đăng ký và bài nộp trong danh sách.'];
    }

    /** "Mở sau 4 ngày 0 giờ 15 phút" / "Còn 22 ngày 11 giờ 13 phút để hoàn thành và nộp bài" (trình duyệt tự cập nhật). */
    private function deadlineText(string $period, ?Carbon $target): ?array
    {
        if ($target === null || $period === 'completed') {
            return null;
        }
        $minutes = max(0, (int) now()->diffInMinutes($target, true));
        $clock = $minutes < 1 ? 'Dưới 1 phút' : trim((intdiv($minutes, 1440) ? intdiv($minutes, 1440).' ngày ' : '').intdiv($minutes % 1440, 60).' giờ '.($minutes % 60).' phút');

        return [
            'prefix' => $period === 'upcoming' ? 'Mở sau' : 'Còn',
            'clock' => $clock,
            'suffix' => $period === 'current' ? ' để hoàn thành và nộp bài' : '',
        ];
    }

    /** Người chủ trì kỳ: người tạo kỳ; kỳ cũ chưa có thì là ban tổ chức của cuộc thi. */
    private function roundHost(Competition $c, CompetitionExam $e): string
    {
        return $this->organizers->roundHostName($e, $this->organizerLabel($c));
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
            // SỬA 25/10 — khảo sát: biết mật khẩu là vào (không cần có trong danh sách).
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

    private function noRoundAction(bool $staff, string $kind): array
    {
        return $staff
            ? ['kind' => 'disabled', 'label' => 'Chưa có kỳ', 'icon' => 'list-checks',
                'title' => 'Bạn chưa tổ chức kỳ nào', 'text' => 'Thêm '.$kind.' mới để quản lý học sinh và thiết lập đề của bạn.']
            : ['kind' => 'disabled', 'label' => 'Chưa có '.$kind, 'icon' => 'lock-keyhole',
                'title' => 'Ban tổ chức đang chuẩn bị', 'text' => 'Các '.$kind.' sẽ hiện ở đây khi ban tổ chức công bố lịch.'];
    }

    /** Khoá học của cuộc thi chưa phát hành → ẩn với học sinh / khách. */
    private function courseHidden(Competition $c): bool
    {
        if (! Competition::supportsCourse() || $c->course_id === null || ! $c->relationLoaded('course') || $c->course === null) {
            return false;
        }
        $status = $c->course->status;

        return ($status instanceof \BackedEnum ? $status->value : (string) $status) !== \App\Enums\ContentStatus::Published->value;
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

        // Người đã có bài nộp ở kỳ này nhưng không có dòng đăng ký (dữ liệu cũ). Người đã bị gỡ (withdrawn) thì không hiện lại.
        $removedIds = CompetitionRegistration::supported()
            ? CompetitionRegistration::query()->where('competition_id', $c->id)->where('status', CompetitionRegistration::STATUS_WITHDRAWN)
                ->pluck('student_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $missing = array_diff($submittedIds, array_keys($byUser), $removedIds);
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
            'records' => Attempt::query()->where('competition_exam_id', $exam->id)->whereNotNull('submitted_at')->count(),
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
    private function questionsOf(CompetitionExam $exam, ?Collection $papers = null): array
    {
        // SỬA 24/10 — kỳ nhiều đề: cột C1…Cn / thống kê theo câu lấy GỘP câu của mọi đề đã chốt (không trùng).
        if ($papers !== null && $papers->isNotEmpty()) {
            $seen = [];
            $out = [];
            foreach ($papers as $paper) {
                foreach ($this->questionsOfAssessment($paper->assessment) as $q) {
                    if ($q['id'] > 0 && ! isset($seen[$q['id']])) {
                        $seen[$q['id']] = true;
                        $out[] = $q;
                    }
                }
            }

            return $out;
        }

        return $this->questionsOfAssessment($exam->assessment);
    }

    private function questionsOfAssessment(?\App\Models\Assessment $assessment): array
    {
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
        // SỬA 27/10 (khách: "thời gian bắt đầu / kết thúc đang sai" — thẻ ghi 24/10 → 25/11 theo lịch chung của cuộc thi
        // trong khi kỳ duy nhất là 27/10 14:00 → 28/10 14:00). Đã có kỳ xếp lịch thì Bắt đầu = giờ mở của kỳ SỚM NHẤT,
        // Kết thúc = giờ đóng của kỳ MUỘN NHẤT — đúng thời gian học sinh làm bài được. Chưa có kỳ nào xếp lịch mới dùng
        // Bắt đầu / Kết thúc nhập cho cuộc thi (SỬA 24/10). Nhãn "còn … ngày" cũng tính theo 2 mốc này.
        $start = $c->examSittings->pluck('starts_at')->filter()->sort()->first() ?? $c->starts_at;
        $end = $c->examSittings->pluck('ends_at')->filter()->sort()->last() ?? $c->ends_at;

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
        // SỬA 25/10 — gắn khoá học thì tên hiển thị là "<loại> · <tên khoá>"; tên riêng đã lưu hiện ở dòng phụ như source.
        if (($custom = $c->customTitle()) !== null) {
            return $custom;
        }
        [$start] = $this->eventRange($c);

        return $start !== null ? 'Mùa '.$start->format('Y') : '';
    }

    public function organizerLabel(Competition $c): string
    {
        return $this->organizers->organizerLabel($c);
    }

    private function num(float $v): string
    {
        $t = number_format($v, 2, '.', '');

        return str_contains($t, '.') ? rtrim(rtrim($t, '0'), '.') : $t;
    }
}

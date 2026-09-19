<?php

namespace App\Services\Student;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\User;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 19/9 (khách: "làm logic khi click đăng ký tham gia thì admin sẽ duyệt") — phía HỌC SINH
 * của luồng đăng ký cuộc thi. Dựng theo đúng khuôn đã dùng cho "xin vào lớp"
 * (App\Services\Student\ClassRoomService::requestJoin(), SỬA 16/9) để hai luồng duyệt trong hệ
 * thống hành xử giống nhau, người dùng không phải học hai cách.
 *
 * Chặn THẬT khi vào thi nằm ở App\Services\AttemptService::competitionEntryDecision() — service
 * này chỉ tạo/đọc đơn.
 */
class CompetitionService
{
    /** Số dòng tối đa của bảng xếp hạng trong không gian thi — đủ để thấy nhóm dẫn đầu, không tải nặng. */
    private const LEADERBOARD_LIMIT = 10;

    public function __construct(
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
    ) {}

    /**
     * Học sinh bấm "Đăng ký tham gia" ở popup chi tiết cuộc thi.
     *
     * Idempotent: bấm nhiều lần không đẻ thêm đơn (unique competition_id + student_id), và đơn
     * từng bị từ chối thì xin lại được — ghi đè đúng dòng cũ, xoá lý do từ chối cũ đi.
     *
     * @throws ValidationException khi cuộc thi đã kết thúc/lưu trữ, đang chờ duyệt, hoặc đã duyệt rồi.
     */
    public function requestJoin(User $user, int $competitionId): Competition
    {
        $this->assertSupported();

        /** @var Competition|null $competition */
        $competition = Competition::find($competitionId);

        if ($competition === null) {
            throw ValidationException::withMessages(['competition_id' => 'Cuộc thi không tồn tại.']);
        }

        // Cuộc thi đã lưu trữ thì không nhận đơn nữa. Các trạng thái còn lại (sắp diễn ra / đang
        // diễn ra / chờ công bố / đã công bố) đều cho đăng ký: đăng ký TRƯỚC giờ thi mới là
        // trường hợp thường gặp nhất, còn sau khi thi xong thì đơn cũng vô hại.
        if ($competition->computedStatus() === \App\Enums\CompetitionStatus::Archived) {
            throw ValidationException::withMessages(['competition_id' => 'Cuộc thi đã lưu trữ — không nhận đăng ký nữa.']);
        }

        $existing = CompetitionRegistration::query()
            ->where('competition_id', $competition->id)
            ->where('student_id', $user->id)
            ->first();

        if ($existing !== null && $existing->isApproved()) {
            throw ValidationException::withMessages(['competition_id' => 'Bạn đã được duyệt tham gia cuộc thi này rồi.']);
        }

        if ($existing !== null && $existing->isPending()) {
            throw ValidationException::withMessages(['competition_id' => 'Đơn của bạn đang chờ ban tổ chức duyệt.']);
        }

        CompetitionRegistration::updateOrCreate(
            ['competition_id' => $competition->id, 'student_id' => $user->id],
            [
                'status' => CompetitionRegistration::STATUS_PENDING,
                'requested_at' => now(),
                // Xin lại sau khi bị từ chối: xoá sạch dấu vết lần duyệt trước, nếu không màn
                // quản trị sẽ hiện một đơn "đang chờ duyệt" mà lại kèm lý do từ chối cũ.
                'approved_at' => null,
                'approved_by' => null,
                'reject_reason' => null,
            ],
        );

        return $competition;
    }

    /**
     * Đơn của học sinh này cho cuộc thi này (null = chưa đăng ký bao giờ).
     *
     * Trả nguyên model để nơi gọi tự đọc trạng thái/lý do từ chối — dùng ở popup chi tiết và ở
     * màn không gian thi.
     */
    public function registrationFor(?User $user, int $competitionId): ?CompetitionRegistration
    {
        if ($user === null || ! CompetitionRegistration::supported()) {
            return null;
        }

        return CompetitionRegistration::query()
            ->where('competition_id', $competitionId)
            ->where('student_id', $user->id)
            ->first();
    }

    /** Học sinh này đã được duyệt vào cuộc thi chưa. */
    public function isApproved(?User $user, int $competitionId): bool
    {
        return $this->registrationFor($user, $competitionId)?->isApproved() ?? false;
    }


    /**
     * student.competitions.room — KHÔNG GIAN THI của MỘT học sinh cho MỘT cuộc thi.
     *
     * Giao diện dựng theo bản mẫu khách gửi (education-main29/src/components/ContestRoomPage.jsx).
     * Bản mẫu chạy trên dữ liệu bịa sẵn (CONTEST_ROUND_REVIEWS, CONTEST_ROUND_LEADERBOARDS,
     * getProblemScores() tự chia điểm ngẫu nhiên...). Ở đây mọi con số đều lấy từ DB:
     *
     *   Bản mẫu                     -> Dữ liệu thật thay vào
     *   ------------------------------------------------------------------
     *   contest.rounds              -> competition_exams (mỗi vòng có giờ riêng)
     *   round.maxScore/participants -> MAX(score)/COUNT(*) của leaderboard_entries vòng đó
     *   getRoundReview()            -> attempts + leaderboard_entries CỦA CHÍNH học sinh này
     *   getRoundLeaderboard()       -> leaderboard_entries scope=competition_exam (top 10)
     *   getProblemScores()          -> điểm THẬT từng câu trong attempt_answers
     *   getLeaderboardAudit()       -> cờ is_provisional của attempt (điểm tạm tính hay đã chốt)
     *   contest.prize               -> không có cột giải thưởng; thay bằng mốc công bố kết quả
     *   "Thông báo ban tổ chức"     -> không có bảng thông báo; sinh từ trạng thái vòng + rules
     *
     * BẢO MẬT: hàm này TỰ kiểm tra quyền, không tin việc giao diện đã ẩn link. Chưa được duyệt
     * là 403 ngay tại đây.
     *
     * @param  int|null  $selectedExamId  vòng đang xem (?vong=), null = tự chọn vòng hợp lý nhất
     */
    public function roomData(?User $user, int $competitionId, ?int $selectedExamId = null): array
    {
        abort_if($user === null, 403);

        /** @var Competition $competition */
        $competition = Competition::query()
            ->with(['examSittings.assessment'])
            ->findOrFail($competitionId);

        /*
         * Chốt chặn quyền. Khi máy chủ CHƯA chạy migration tạo bảng đăng ký thì cho vào —
         * giống hệt AttemptService::isApprovedForCompetition(), để bản triển khai cũ không bị
         * khoá cứng chỉ vì thiếu migration. Chạy migration xong là luật duyệt có hiệu lực ngay.
         */
        if (CompetitionRegistration::supported() && ! $this->isApproved($user, $competition->id)) {
            abort(403, 'Bạn chưa được ban tổ chức duyệt tham gia cuộc thi này.');
        }

        $exams = $competition->examSittings;
        $examIds = $exams->pluck('id')->all();

        // ── Số liệu chung từng vòng (1 truy vấn cho tất cả vòng) ───────────
        $examStats = $this->leaderboardEntries->statsForCompetitionExams($examIds)->keyBy('competition_exam_id');

        // ── Kết quả của CHÍNH học sinh này ở từng vòng (2 truy vấn) ────────
        $myAttempts = $examIds === [] ? collect() : Attempt::query()
            ->where('user_id', $user->id)
            ->whereIn('competition_exam_id', $examIds)
            ->orderBy('id')
            ->get()
            // Nhiều lượt cùng 1 vòng (đề cho làm lại) -> giữ lượt MỚI NHẤT, vì đó là điểm đang
            // được tính. keyBy giữ phần tử cuối cùng nên orderBy('id') ở trên là cố ý.
            ->keyBy('competition_exam_id');

        $myEntries = $examIds === [] ? collect() : \App\Models\LeaderboardEntry::query()
            ->where('scope', 'competition_exam')
            ->where('user_id', $user->id)
            ->whereIn('competition_exam_id', $examIds)
            ->get()
            ->keyBy('competition_exam_id');

        // ── Dựng danh sách vòng ────────────────────────────────────────────
        $rounds = [];
        $order = 0;
        foreach ($exams as $exam) {
            $order++;
            $examStatus = $exam->computedStatus();          // upcoming | ongoing | ended
            $stat = $examStats->get($exam->id);
            $attempt = $myAttempts->get($exam->id);
            $entry = $myEntries->get($exam->id);

            $rounds[] = [
                'id' => $exam->id,
                'order' => $order,
                'label' => $exam->displayTitle(),
                'shortLabel' => $exam->title ?: 'Vòng '.$order,
                'date' => $exam->starts_at?->format('d/m/Y') ?? 'Chưa xếp lịch',
                'timeRange' => $this->timeRangeLabel($exam),
                // Bản mẫu dùng completed|current|upcoming — map thẳng từ giờ thật của vòng.
                'status' => $examStatus === 'ended' ? 'completed' : ($examStatus === 'ongoing' ? 'current' : 'upcoming'),
                'assessmentId' => $exam->assessment_id,
                'totalPoints' => (float) ($exam->assessment?->total_points ?: 100),
                'durationMinutes' => $exam->assessment?->duration_minutes,
                'participants' => $stat !== null ? (int) $stat->participants : 0,
                'maxScore' => $stat !== null ? (float) $stat->max_score : null,
                'myScore' => $attempt?->submitted_at !== null ? (float) $attempt->total_score : null,
                'myRank' => $entry?->rank !== null ? (int) $entry->rank : null,
                'myProvisional' => (bool) ($attempt?->is_provisional ?? false),
                'attempted' => $attempt !== null && $attempt->submitted_at !== null,
                'gradedAt' => $entry?->computed_at?->format('H:i d/m/Y'),
            ];
        }

        // Thêm nhãn "review" (ô trạng thái + ghi chú) cho từng vòng — thay CONTEST_ROUND_REVIEWS.
        foreach ($rounds as $i => $r) {
            $rounds[$i]['review'] = $this->roundReview($r, $competition);
        }

        // ── Vòng đang xem ──────────────────────────────────────────────────
        $selected = null;
        if ($selectedExamId !== null) {
            foreach ($rounds as $r) {
                if ($r['id'] === $selectedExamId) { $selected = $r; break; }
            }
        }
        if ($selected === null) {
            // Ưu tiên vòng đang diễn ra -> vòng đã kết thúc gần nhất -> vòng đầu tiên.
            foreach ($rounds as $r) {
                if ($r['status'] === 'current') { $selected = $r; break; }
            }
        }
        if ($selected === null) {
            foreach (array_reverse($rounds) as $r) {
                if ($r['status'] === 'completed') { $selected = $r; break; }
            }
        }
        if ($selected === null && $rounds !== []) {
            $selected = $rounds[0];
        }

        // ── Bảng xếp hạng + điểm từng câu của vòng đang xem ────────────────
        $problems = [];
        $leaderboard = [];
        if ($selected !== null) {
            $selectedExam = $exams->firstWhere('id', $selected['id']);
            $problems = $this->problemsOf($selectedExam);
            $leaderboard = $this->leaderboardOf($selected, $problems, $user);

            /*
             * Số câu ĐÃ CÓ ĐIỂM của chính học sinh ở vòng đang xem — thay cho việc bản mẫu
             * đếm bài "Đang làm"/"Đã nộp" từ mảng bịa. Đếm theo graded_at (đã chấm) chứ không
             * theo số dòng answer, vì câu đã trả lời mà máy chấm chưa chạy xong thì chưa tính
             * là hoàn thành — đúng nghĩa thanh "Tỷ lệ hoàn thành".
             */
            $mine = $myAttempts->get($selected['id']);
            $selected['answeredCount'] = $mine === null ? 0 : AttemptAnswer::query()
                ->where('attempt_id', $mine->id)
                ->whereNotNull('graded_at')
                ->count();
        }

        $completedRounds = count(array_filter($rounds, fn ($r) => $r['status'] === 'completed'));
        $hasActiveRound = array_filter($rounds, fn ($r) => $r['status'] === 'current') !== [];

        return [
            'competition' => $competition,
            'competitionTitle' => $competition->title,
            'editionLabel' => $competition->starts_at !== null ? 'Mùa '.$competition->starts_at->format('Y') : 'Chưa xếp lịch',
            'statusLabel' => $this->competitionStatusLabel($competition),
            // Không có cột ảnh cho cuộc thi — dùng đúng bộ ảnh của bản mẫu, chia đều theo id để
            // mỗi cuộc thi luôn ra cùng một ảnh (không nhảy ảnh mỗi lần tải trang).
            'image' => asset('assets/contest-img-'.(($competition->id % 3) + 1).'.png'),
            'rounds' => $rounds,
            'selectedRound' => $selected,
            'completedRounds' => $completedRounds,
            'hasActiveRound' => $hasActiveRound,
            'problems' => $problems,
            'leaderboard' => $leaderboard,
            'studentStats' => $this->studentStats($rounds, $selected, $problems),
            // Bản mẫu có ô "Giải thưởng" — hệ thống không có cột đó; thay bằng mốc công bố kết
            // quả, thông tin thật mà thí sinh quan tâm đúng ở vị trí ấy.
            'awardLabel' => $competition->publish_result_at !== null
                ? 'Công bố kết quả '.$competition->publish_result_at->format('H:i d/m/Y')
                : 'Kết quả công bố ngay khi kết thúc',
            'announcement' => $this->announcement($competition, $selected),
            'backUrl' => route('competitions.show', $competition->id),
            'leaderboardUrl' => route('leaderboard.index', ['competition' => $competition->id]),
        ];
    }


    /**
     * SỬA 19/9 (2) — bối cảnh CUỘC THI cho màn làm bài riêng (student.competitions.exam).
     *
     * CHỈ trả thông tin để HIỂN THỊ + 2 lớp kiểm tra quyền rẻ tiền (đã duyệt chưa, vòng thi có
     * đúng của cuộc thi này không). TUYỆT ĐỐI không đụng tới việc mở/tiếp tục lượt làm bài —
     * việc đó vẫn do Student\AssessmentService::buildTakeData() + AttemptService làm y như
     * đường cũ, nên luật giờ giấc, số lượt làm lại và chấm điểm không đổi một dòng nào.
     *
     * Khoá trả về đều có tiền tố "contest" để trộn chung với mảng của buildTakeData() mà không
     * đè nhầm khoá nào của nó (nó đã có 'examCode', 'attempt', 'questions'...).
     *
     * @return array<string, mixed>
     */
    public function examContext(?User $user, int $competitionId, int $examId): array
    {
        abort_if($user === null, 403);

        /** @var Competition $competition */
        $competition = Competition::query()->findOrFail($competitionId);

        /*
         * Chưa được duyệt là chuyện NGHIỆP VỤ bình thường (đơn còn chờ, hoặc bị từ chối), nên
         * ném ValidationException để controller hiện trang "Chưa thể vào làm bài" quen thuộc,
         * KHÔNG abort(403) — trang 403 trắng trơn của Laravel không nói được phải làm gì tiếp.
         * Ngược lại, id vòng thi sai/ghép từ cuộc thi khác mới là truy cập bất thường -> 404.
         */
        if (CompetitionRegistration::supported() && ! $this->isApproved($user, $competition->id)) {
            throw ValidationException::withMessages([
                'attempt' => 'Bạn chưa được ban tổ chức duyệt tham gia cuộc thi này — hãy gửi đăng ký và chờ duyệt trước khi vào thi.',
            ]);
        }

        /** @var CompetitionExam|null $exam */
        $exam = CompetitionExam::query()
            ->with('assessment')
            ->where('competition_id', $competition->id)   // chặn ghép id vòng của cuộc thi KHÁC
            ->find($examId);

        abort_if($exam === null, 404);

        if ($exam->assessment_id === null) {
            abort(404, 'Vòng thi này chưa được ban tổ chức gắn đề.');
        }

        $examStatus = $exam->computedStatus();   // upcoming | ongoing | ended

        return [
            'assessmentId' => (int) $exam->assessment_id,
            'contestId' => $competition->id,
            'contestTitle' => $competition->title,
            'contestRoundLabel' => $exam->displayTitle(),
            'contestRoundShort' => $exam->title ?: 'Vòng thi',
            'contestStatusLabel' => match ($examStatus) {
                'ongoing' => 'Đang diễn ra',
                'ended' => 'Đã kết thúc',
                default => 'Chưa mở',
            },
            'contestTimeRange' => $this->timeRangeLabel($exam),
            'contestEndsAtLabel' => $exam->ends_at?->format('H:i d/m/Y'),
            // Thể lệ do admin nhập; rỗng thì view tự ẩn mục "Thể lệ" thay vì in ô trống.
            'contestRules' => trim((string) ($competition->rules ?? '')) ?: null,
            'contestRoomUrl' => route('student.competitions.room', ['competition' => $competition->id, 'vong' => $exam->id]),
            'contestLeaderboardUrl' => route('leaderboard.index', ['competition' => $competition->id, 'exam' => $exam->id]),
        ];
    }

    /** "08:00 10/09 → 11:00 10/09"; thiếu mốc nào thì nói thẳng là chưa xếp. */
    private function timeRangeLabel(CompetitionExam $exam): string
    {
        if ($exam->starts_at === null && $exam->ends_at === null) {
            return 'Chưa xếp lịch';
        }

        return ($exam->starts_at?->format('H:i d/m/Y') ?? '—').' → '.($exam->ends_at?->format('H:i d/m/Y') ?? '—');
    }

    private function competitionStatusLabel(Competition $competition): string
    {
        return match ($competition->computedStatus()->value) {
            'upcoming' => 'Sắp diễn ra',
            'ongoing' => 'Đang diễn ra',
            'pending_publish' => 'Chờ công bố kết quả',
            'published' => 'Đã công bố',
            default => 'Lưu trữ',
        };
    }

    /**
     * Ô "trạng thái + ghi chú" của từng vòng (bản mẫu: getRoundReview(), vốn là bảng bịa sẵn).
     *
     * @param  array<string, mixed>  $round
     * @return array{status: string, note: string, tone: string, score: string, rank: string}
     */
    private function roundReview(array $round, Competition $competition): array
    {
        $scoreLabel = $round['myScore'] !== null
            ? $this->trimNumber($round['myScore']).'/'.$this->trimNumber($round['totalPoints'])
            : '—';
        $rankLabel = $round['myRank'] !== null
            ? $round['myRank'].($round['participants'] > 0 ? '/'.number_format($round['participants'], 0, ',', '.') : '')
            : '—';

        if ($round['status'] === 'upcoming') {
            return ['status' => 'Chưa mở', 'note' => 'Vòng thi chưa bắt đầu — '.$round['timeRange'], 'tone' => 'locked', 'score' => '—', 'rank' => '—'];
        }

        if ($round['status'] === 'current') {
            return [
                'status' => $round['attempted'] ? 'Đã nộp · đang diễn ra' : 'Đang diễn ra',
                'note' => $round['attempted'] ? 'Bài đã nộp, chờ vòng kết thúc để chốt thứ hạng.' : 'Vòng đang mở — bạn có thể vào thi ngay.',
                'tone' => 'current',
                'score' => $scoreLabel,
                'rank' => $rankLabel,
            ];
        }

        // Vòng đã kết thúc.
        if (! $round['attempted']) {
            return ['status' => 'Không dự thi', 'note' => 'Bạn không có bài nộp ở vòng này.', 'tone' => 'locked', 'score' => '—', 'rank' => '—'];
        }

        if ($round['myProvisional'] || $competition->computedStatus()->value === 'pending_publish') {
            return ['status' => 'Chờ công bố kết quả', 'note' => 'Điểm tạm tính, ban tổ chức đang rà soát.', 'tone' => 'review', 'score' => $scoreLabel, 'rank' => $rankLabel];
        }

        return [
            'status' => 'Đã hoàn thành',
            'note' => $round['gradedAt'] !== null ? 'Kết quả đã chốt lúc '.$round['gradedAt'].'.' : 'Kết quả đã được ghi nhận.',
            'tone' => 'success',
            'score' => $scoreLabel,
            'rank' => $rankLabel,
        ];
    }

    /**
     * Danh sách câu hỏi của vòng đang xem — thay mảng "problems" bịa của bản mẫu.
     *
     * Đề dạng PDF (content_mode = pdf_answer_sheet) không có assessment_items; khi đó trả mảng
     * rỗng và bảng xếp hạng sẽ bỏ luôn các cột "Bài 1..n" thay vì hiện cột trống.
     *
     * @return array<int, array{id: int, order: int, title: string, points: int}>
     */
    private function problemsOf(?CompetitionExam $exam): array
    {
        $assessment = $exam?->assessment;

        if ($assessment === null) {
            return [];
        }

        $problems = [];
        $order = 0;
        // Chỉ nạp đúng 3 cột của câu hỏi (bảng questions KHÔNG có cột "stem" — tên cột là
        // "title"; chọn nhầm tên cột sẽ đổ SQL "Unknown column" ngay khi mở trang).
        foreach ($assessment->items()->with('question:id,title,points')->get() as $item) {
            $order++;
            $problems[] = [
                'id' => (int) $item->question_id,
                'order' => $order,
                // Tiêu đề thật của câu hỏi, dùng làm tooltip cho cột "Câu N" trong bảng xếp hạng.
                'title' => $item->question?->title ?: ('Câu '.$order),
                'points' => $item->effectivePoints(),
            ];
        }

        return $problems;
    }

    /**
     * Bảng xếp hạng vòng đang xem + điểm THẬT từng câu (bản mẫu chia điểm ngẫu nhiên bằng
     * getProblemScores(); ở đây đọc attempt_answers).
     *
     * Ẩn danh giống bảng xếp hạng công khai — chỉ chính người xem thấy tên mình. Hệ thống không
     * có cột "đồng ý hiện tên" và phần lớn người học dưới 18 tuổi, nên mặc định là bảo vệ.
     *
     * @param  array<string, mixed>  $round
     * @param  array<int, array{id: int, order: int, title: string, points: int}>  $problems
     * @return array<int, array<string, mixed>>
     */
    private function leaderboardOf(array $round, array $problems, User $viewer): array
    {
        $entries = $this->leaderboardEntries->entriesForCompetitionExam($round['id'])->take(self::LEADERBOARD_LIMIT);

        if ($entries->isEmpty()) {
            return [];
        }

        $userIds = $entries->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        // Lượt làm bài của đúng những người trong bảng, ở đúng vòng này (1 truy vấn).
        $attempts = Attempt::query()
            ->whereIn('user_id', $userIds)
            ->where('competition_exam_id', $round['id'])
            ->orderBy('id')
            ->get()
            ->keyBy('user_id'); // nhiều lượt -> giữ lượt mới nhất, xem chú thích ở roomData()

        // Điểm từng câu của những lượt đó (1 truy vấn). Khoá: attempt_id -> question_id -> score.
        $scoreByAttemptQuestion = [];
        if ($attempts->isNotEmpty() && $problems !== []) {
            $answers = AttemptAnswer::query()
                ->whereIn('attempt_id', $attempts->pluck('id')->all())
                ->get(['attempt_id', 'question_id', 'score']);

            foreach ($answers as $answer) {
                $scoreByAttemptQuestion[(int) $answer->attempt_id][(int) $answer->question_id] = (float) $answer->score;
            }
        }

        $rows = [];
        $index = 0;
        foreach ($entries as $entry) {
            $isViewer = (int) $entry->user_id === $viewer->id;
            $attempt = $attempts->get($entry->user_id);
            $perProblem = [];

            foreach ($problems as $problem) {
                $perProblem[] = $attempt !== null
                    ? ($scoreByAttemptQuestion[(int) $attempt->id][$problem['id']] ?? null)
                    : null;
            }

            // Cờ "cần rà soát" của bản mẫu vốn là bịa; ở đây dùng dữ liệu thật: điểm tạm tính
            // (is_provisional) nghĩa là chưa chốt, còn lại là đã đối soát.
            $provisional = (bool) ($attempt?->is_provisional ?? false);

            $rows[] = [
                'rank' => $entry->rank !== null ? (int) $entry->rank : ($index + 1),
                'name' => $isViewer ? ($viewer->name ?: 'Bạn') : 'Học viên đã xác thực',
                'isViewer' => $isViewer,
                'note' => $entry->computed_at !== null ? 'Chấm lúc '.$entry->computed_at->format('H:i d/m/Y') : 'Chưa ghi nhận giờ chấm',
                'score' => (float) $entry->score,
                'problemScores' => $perProblem,
                'auditLabel' => $provisional ? 'Tạm tính' : 'Đã đối soát',
                'auditFlagged' => $provisional,
                'avatar' => asset('assets/rank-avatar-'.(($index % 5) + 1).'.png'),
            ];
            $index++;
        }

        return $rows;
    }

    /**
     * 4 ô "Kết quả cá nhân" bên phải. Bản mẫu đếm bài "Đang làm"/"Đã nộp" từ dữ liệu bịa; ở
     * đây đếm số câu ĐÃ CÓ ĐIỂM trong lượt làm của chính học sinh ở vòng đang xem.
     *
     * @param  array<int, array<string, mixed>>  $rounds
     * @param  array<string, mixed>|null  $selected
     * @param  array<int, array<string, mixed>>  $problems
     * @return array<int, array{label: string, value: string, meta: string, tone: string}>
     */
    private function studentStats(array $rounds, ?array $selected, array $problems): array
    {
        $participated = count(array_filter($rounds, fn ($r) => $r['attempted']));
        $scores = array_values(array_filter(array_map(fn ($r) => $r['myScore'], $rounds), fn ($v) => $v !== null));
        $best = $scores === [] ? '—' : $this->trimNumber(max($scores));

        $answered = $selected !== null ? (int) ($selected['answeredCount'] ?? 0) : 0;
        $total = count($problems);
        $completion = $total > 0 ? (int) round($answered / $total * 100) : 0;

        return [
            ['label' => 'Vòng đã dự thi', 'value' => (string) $participated, 'meta' => 'trong '.count($rounds).' vòng', 'tone' => 'teal'],
            ['label' => 'Điểm cao nhất', 'value' => $best, 'meta' => 'trên các vòng đã thi', 'tone' => 'amber'],
            ['label' => 'Câu đã có điểm', 'value' => $answered.'/'.$total, 'meta' => 'ở vòng đang xem', 'tone' => 'blue'],
            ['label' => 'Tỷ lệ hoàn thành', 'value' => $completion.'%', 'meta' => 'tiến độ vòng đang xem', 'tone' => 'green'],
        ];
    }

    /**
     * Ô "Thông báo ban tổ chức". Hệ thống KHÔNG có bảng thông báo cuộc thi — bịa một thông báo
     * là sai nguyên tắc "dữ liệu lấy từ database". Thay vào đó sinh câu chữ từ dữ kiện có thật
     * (trạng thái vòng đang xem + mốc công bố kết quả), và nếu admin đã nhập "Thể lệ"
     * (competitions.rules) thì ưu tiên hiện đúng thể lệ đó.
     *
     * @param  array<string, mixed>|null  $selected
     * @return array{title: string, body: string, meta: string}
     */
    private function announcement(Competition $competition, ?array $selected): array
    {
        $rules = trim((string) ($competition->rules ?? ''));

        if ($rules !== '') {
            return [
                'title' => 'Thể lệ do ban tổ chức công bố',
                'body' => \Illuminate\Support\Str::limit($rules, 320),
                'meta' => 'Cập nhật '.($competition->updated_at?->format('H:i d/m/Y') ?? '—'),
            ];
        }

        $body = match ($selected['status'] ?? 'upcoming') {
            'current' => 'Vòng thi đang mở. Hãy hoàn thành bài trước '.($selected['timeRange'] ?? 'giờ đóng').' để được tính điểm.',
            'completed' => $competition->publish_result_at !== null
                ? 'Vòng thi đã kết thúc. Kết quả chính thức được công bố lúc '.$competition->publish_result_at->format('H:i d/m/Y').'.'
                : 'Vòng thi đã kết thúc. Kết quả được cập nhật ngay khi ban tổ chức chấm xong.',
            default => 'Vòng thi chưa mở. Thời gian dự kiến: '.($selected['timeRange'] ?? 'chưa xếp lịch').'.',
        };

        return [
            'title' => 'Cập nhật vòng thi',
            'body' => $body,
            'meta' => 'Cập nhật '.($competition->updated_at?->format('H:i d/m/Y') ?? '—'),
        ];
    }

    /** 72.50 -> "72,5"; 80.00 -> "80". Dùng dấu phẩy thập phân kiểu Việt Nam. */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function assertSupported(): void
    {
        if (! CompetitionRegistration::supported()) {
            throw ValidationException::withMessages([
                'competition_id' => 'Máy chủ chưa chạy migration cho tính năng đăng ký cuộc thi — báo quản trị viên chạy "php artisan migrate".',
            ]);
        }
    }
}

<?php

namespace App\Services\Public;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cuộc thi công khai (PUB-08, 11.1 "Menu Cuộc thi": lịch, thể lệ, đề/bộ bài, quy tắc công
 * bố, trạng thái Sắp diễn ra→Đang diễn ra→Chờ công bố→Đã công bố→Lưu trữ).
 */
class CompetitionService
{
    private const STATUS_META = [
        'upcoming' => ['label' => 'Sắp diễn ra', 'tone' => 'info'],
        'ongoing' => ['label' => 'Đang diễn ra', 'tone' => 'success'],
        'pending_publish' => ['label' => 'Chờ công bố', 'tone' => 'neutral'],
        'published' => ['label' => 'Đã công bố', 'tone' => 'neutral'],
        'archived' => ['label' => 'Lưu trữ', 'tone' => 'neutral'],
    ];

    /** Nhãn trạng thái RIÊNG cho từng kỳ thi (App\Models\CompetitionExam::computedStatus()) — 3 trạng thái đơn giản hơn cấp cuộc thi (không có "chờ/đã công bố" ở cấp kỳ thi). */
    private const EXAM_STATUS_META = [
        'upcoming' => ['label' => 'Sắp diễn ra', 'tone' => 'info'],
        'ongoing' => ['label' => 'Đang diễn ra', 'tone' => 'success'],
        'ended' => ['label' => 'Đã kết thúc', 'tone' => 'neutral'],
    ];

    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private AttemptRepositoryInterface $attempts,
        // SUA 12/9 - man Cuoc thi moi can diem cao nhat tung vong + Top 5 tong hop.
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
    ) {}

    /**
     * competitions.index — danh sách cuộc thi/khảo sát công khai.
     *
     * SỬA 12/9 — dựng lại màn Cuộc thi theo ĐÚNG source giao diện khách gửi
     * (education-main/src/components/ContestsPage.jsx). Bản mẫu chạy trên dữ liệu giả; ở đây
     * mọi con số đều lấy từ DB thật:
     *   · "các vòng thi"     -> competition_exams (đã có sẵn, mỗi vòng tự có giờ riêng)
     *   · "điểm cao nhất"    -> MAX(leaderboard_entries.score) của ĐÚNG vòng đó
     *   · "Top 5 thủ khoa"   -> leaderboard_entries scope=competition, đã xếp hạng
     *   · "số người tham gia"-> đếm leaderboard_entries scope=competition
     *   · "Đã tham gia"      -> attempts đã nộp của chính người đang xem
     *
     * KHÁC bản mẫu ở 1 điểm lớn, theo đúng yêu cầu khách (UI nào chưa khớp logic cũ thì sửa
     * UI cho khớp logic): bản mẫu có luồng "gửi đăng ký -> BTC duyệt -> vào phòng thi", nhưng
     * hệ thống KHÔNG có bảng đăng ký/duyệt nào cả — vào thi là vào thẳng đề tham chiếu khi
     * đang trong khung giờ của vòng (xem showData()). Vì vậy dải 3 bước được giữ nguyên hình
     * dáng nhưng đổi thành 3 bước CÓ THẬT: Đăng nhập -> Vào phòng thi -> Xem kết quả.
     *
     * Tên học viên trong Top 5 được ẩn danh y như bảng xếp hạng công khai (bảo vệ dữ liệu
     * trẻ em) — chỉ chính người đang đăng nhập mới thấy tên mình.
     */
    public function indexData(?User $viewer = null): array
    {
        $competitions = $this->competitions->query()
            // Đề "cấu trúc" đếm số câu ở assessment_items; đề PDF (content_mode =
            // pdf_answer_sheet) không có items mà đếm theo phiếu đáp án — đếm cả hai rồi lấy
            // cái nào có, để không hiện "0 câu" với đề PDF.
            ->with(['examSittings.assessment' => fn ($q) => $q->withCount(['items', 'answerKeys'])])
            // Chỉ đếm dòng xếp hạng TỔNG (scope=competition); nếu đếm cả scope=competition_exam
            // thì cuộc thi nhiều vòng sẽ hiện số người gấp đôi/gấp ba số người thật.
            ->withCount(['leaderboardEntries' => fn ($q) => $q->where('scope', 'competition')])
            ->latest('starts_at')
            ->limit(30)
            ->get();

        $examIds = $competitions->flatMap(fn (Competition $c) => $c->examSittings->pluck('id'))->all();
        $competitionIds = $competitions->pluck('id')->all();

        // Điểm cao nhất + số lượt của TỪNG vòng, gộp 1 truy vấn cho tất cả vòng của cả trang.
        $examStats = $this->leaderboardEntries->statsForCompetitionExams($examIds)->keyBy('competition_exam_id');

        // Top 5 mỗi cuộc thi — lấy 1 lần rồi nhóm ở PHP (danh sách công khai tối đa 30 cuộc thi).
        $topByCompetition = $this->leaderboardEntries->entriesForCompetitions($competitionIds)->groupBy('competition_id');

        $submittedCompetitionIds = $viewer !== null
            ? $this->attempts->submittedCompetitionIdsForUser($viewer->id, $competitionIds)
            : [];
        $submittedExamIds = $viewer !== null
            ? $this->attempts->submittedCompetitionExamIdsForUser($viewer->id, $examIds)
            : [];

        $isStudent = $viewer !== null && $viewer->hasRole(Role::STUDENT);

        /*
         * SỬA 19/9 (khách: "click đăng ký tham gia thì admin sẽ duyệt, duyệt xong mới vào
         * được") — trạng thái ĐƠN ĐĂNG KÝ của chính người đang xem, nạp MỘT truy vấn cho tất
         * cả cuộc thi rồi tra trong bộ nhớ (thẻ nào cũng cần, đừng bắn N truy vấn).
         * Khoá = competition_id, giá trị = 'pending'|'approved'|'rejected'|'withdrawn'.
         */
        $registrationByCompetition = $this->registrationStatuses($viewer, $competitions->pluck('id')->all());

        /*
         * SỬA 19/9 (12) — SỐ THÍ SINH ĐÃ ĐƯỢC DUYỆT của từng cuộc thi, gộp MỘT truy vấn cho cả
         * trang. Khác registrationStatuses() ở trên: cái đó là đơn của RIÊNG người đang xem,
         * cái này là tổng của MỌI người — dùng cho ô "N người" trên thẻ cuộc thi.
         */
        $approvedByCompetition = CompetitionRegistration::supported() && $competitionIds !== []
            ? CompetitionRegistration::query()
                ->selectRaw('competition_id, COUNT(*) as approved')
                ->where('status', CompetitionRegistration::STATUS_APPROVED)
                ->whereIn('competition_id', $competitionIds)
                ->groupBy('competition_id')
                ->pluck('approved', 'competition_id')
                ->all()
            : [];

        $cards = $competitions->map(fn (Competition $c) => $this->mapContestCard(
            $c,
            $examStats,
            $topByCompetition->get($c->id),
            $viewer,
            $isStudent,
            in_array($c->id, $submittedCompetitionIds, true),
            $submittedExamIds,
            $registrationByCompetition[$c->id] ?? null,
            (int) ($approvedByCompetition[$c->id] ?? 0),
        ))->all();

        return [
            'competitions' => $cards,
            'heroPanel' => $this->heroPanel($cards),
            'viewerName' => $viewer?->name,
        ];
    }

    /**
     * Ô đếm ngược ở góc phải banner. Bản mẫu ghi cứng "02 : 45 : 18"; ở đây lấy mốc giờ THẬT:
     * ưu tiên cuộc thi đang diễn ra (đếm tới giờ đóng), không có thì lấy cuộc thi sắp mở gần
     * nhất (đếm tới giờ mở). deadline trả về dạng timestamp để Alpine tự chạy đồng hồ; null
     * nghĩa là không có gì để đếm (view hiện dấu "—" thay vì số 0 gây hiểu lầm).
     */
    /**
     * SỬA 19/9 — trạng thái đơn đăng ký cuộc thi của MỘT người xem, cho nhiều cuộc thi cùng lúc.
     *
     * Trả mảng rỗng khi chưa đăng nhập hoặc máy chủ chưa chạy migration tạo bảng — khi đó mọi
     * chỗ dùng sẽ coi như "chưa đăng ký", và AttemptService cũng cho qua như hành vi cũ, nên
     * hai bên vẫn khớp nhau.
     *
     * @param  array<int, int>  $competitionIds
     * @return array<int, string> competition_id => status
     */
    private function registrationStatuses(?User $viewer, array $competitionIds): array
    {
        if ($viewer === null || $competitionIds === [] || ! CompetitionRegistration::supported()) {
            return [];
        }

        return CompetitionRegistration::query()
            ->where('student_id', $viewer->id)
            ->whereIn('competition_id', $competitionIds)
            ->pluck('status', 'competition_id')
            ->all();
    }

    /**
     * SỬA 19/9 — học sinh đã được ban tổ chức duyệt đơn chưa?
     *
     * Tách riêng một hàm vì luật này bị hỏi ở NHIỀU chỗ (canJoin của từng vòng, canJoinDirectly
     * của trang chi tiết, nút hành động chính của thẻ cuộc thi). Nếu mỗi chỗ tự viết lại điều
     * kiện thì sớm muộn sẽ lệch nhau.
     *
     * QUAN TRỌNG: hàm này PHẢI khớp đúng AttemptService::isApprovedForCompetition().
     * Giao diện nới lỏng hơn máy chủ = hiện nút rồi bấm vào báo lỗi (lỗi thật sự).
     * Vì vậy khi máy chủ CHƯA chạy migration tạo bảng đăng ký, cả hai bên cùng trả true để
     * giữ nguyên hành vi cũ (cuộc thi mở là vào được), thay vì khoá sạch cả hệ thống.
     */
    private function isApprovedRegistration(?string $status): bool
    {
        if (! CompetitionRegistration::supported()) {
            return true;
        }

        return $status === CompetitionRegistration::STATUS_APPROVED;
    }

    private function heroPanel(array $cards): array
    {
        $ongoing = null;
        $upcoming = null;

        foreach ($cards as $card) {
            if ($card['statusValue'] === 'ongoing' && $card['endsAtTimestamp'] !== null && $ongoing === null) {
                $ongoing = $card;
            }

            if ($card['statusValue'] === 'upcoming' && $card['startsAtTimestamp'] !== null) {
                if ($upcoming === null || $card['startsAtTimestamp'] < $upcoming['startsAtTimestamp']) {
                    $upcoming = $card;
                }
            }
        }

        if ($ongoing !== null) {
            return [
                'eyebrow' => 'Đang mở · còn lại',
                'deadline' => $ongoing['endsAtTimestamp'],
                'note' => Str::limit($ongoing['title'], 42),
            ];
        }

        if ($upcoming !== null) {
            return [
                'eyebrow' => 'Sắp mở sau',
                'deadline' => $upcoming['startsAtTimestamp'],
                'note' => Str::limit($upcoming['title'], 42),
            ];
        }

        return [
            'eyebrow' => 'Lịch thi',
            'deadline' => null,
            'note' => 'Chưa có sự kiện nào đang mở',
        ];
    }

    /** Nhãn + màu badge trạng thái, khớp bảng màu của source (ContestsPage.jsx statusStyle). */
    private const CARD_STATUS_STYLE = [
        'upcoming' => ['label' => 'Sắp diễn ra', 'style' => 'bg-amber-100 text-amber-800 border-amber-300'],
        'ongoing' => ['label' => 'Đang diễn ra 🔥', 'style' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
        'pending_publish' => ['label' => 'Chờ công bố kết quả', 'style' => 'bg-violet-100 text-violet-800 border-violet-300'],
        'published' => ['label' => 'Đã công bố', 'style' => 'bg-blue-100 text-blue-800 border-blue-300'],
        'archived' => ['label' => 'Lưu trữ', 'style' => 'bg-slate-100 text-slate-700 border-slate-300'],
    ];

    /**
     * 1 thẻ cuộc thi cho màn danh sách + dữ liệu cho hộp chi tiết (bản mẫu mở modal, nên toàn
     * bộ số liệu của modal được nạp sẵn ở đây, không gọi thêm truy vấn khi bấm xem).
     */
    private function mapContestCard(
        Competition $c,
        \Illuminate\Support\Collection $examStats,
        ?\Illuminate\Support\Collection $topEntries,
        ?User $viewer,
        bool $isStudent,
        bool $hasSubmitted,
        array $submittedExamIds,
        // SỬA 19/9 — trạng thái đơn đăng ký của người xem với CHÍNH cuộc thi này (null = chưa
        // đăng ký, hoặc chưa đăng nhập, hoặc máy chủ chưa chạy migration).
        ?string $registrationStatus = null,
        // SỬA 19/9 (12) — số thí sinh ĐÃ ĐƯỢC DUYỆT của cuộc thi này (đếm gộp ở indexData()).
        int $approvedRegistrations = 0,
    ): array {
        $statusValue = $c->computedStatus()->value;
        $meta = self::CARD_STATUS_STYLE[$statusValue] ?? ['label' => $statusValue, 'style' => 'bg-slate-100 text-slate-700 border-slate-300'];
        $isSurvey = $c->type->value !== 'contest';

        // ── Các vòng thi ───────────────────────────────────────────────────
        $rounds = [];
        $order = 0;
        foreach ($c->examSittings as $exam) {
            $order++;
            $examStatus = $exam->computedStatus();
            $stat = $examStats->get($exam->id);

            $rounds[] = [
                'id' => $exam->id,
                'order' => $order,
                'label' => $exam->displayTitle(),
                'shortLabel' => $exam->title ?: 'Vòng '.$order,
                'date' => $exam->starts_at?->format('d/m/Y') ?? 'Chưa xếp lịch',
                // Bản mẫu dùng 3 trạng thái completed|current|upcoming — map thẳng từ giờ thật của vòng.
                'status' => $examStatus === 'ended' ? 'completed' : ($examStatus === 'ongoing' ? 'current' : 'upcoming'),
                'maxScore' => $stat !== null ? (float) $stat->max_score : null,
                'participants' => $stat !== null ? (int) $stat->participants : 0,
                'assessmentId' => $exam->assessment_id,
                'durationMinutes' => $exam->assessment?->duration_minutes,
                'problemsCount' => max((int) ($exam->assessment?->items_count ?? 0), (int) ($exam->assessment?->answer_keys_count ?? 0)),
                'totalPoints' => $exam->assessment?->total_points,
                'alreadyAttempted' => in_array($exam->id, $submittedExamIds, true),
                /*
                 * SỬA 19/9 — thêm điều kiện ĐÃ ĐƯỢC DUYỆT. Chặn thật nằm ở
                 * AttemptService::competitionEntryDecision() (đã sửa cùng ngày); dòng này chỉ
                 * để giao diện nói đúng cùng một luật — 2 nơi PHẢI luôn khớp, nếu không nút
                 * hiện "Vào thi" mà bấm vào lại báo lỗi, đúng kiểu lỗi đã gặp hồi 24/8.
                 */
                'canJoin' => $isStudent
                    && $this->isApprovedRegistration($registrationStatus)
                    && $exam->assessment_id !== null
                    && $examStatus === 'ongoing'
                    && ! in_array($exam->id, $submittedExamIds, true),
            ];
        }

        // Vòng "đang xem": ưu tiên vòng đang diễn ra, rồi vòng sắp tới, cuối cùng là vòng cuối.
        $currentRound = null;
        foreach ($rounds as $r) {
            if ($r['status'] === 'current') { $currentRound = $r; break; }
        }
        if ($currentRound === null) {
            foreach ($rounds as $r) {
                if ($r['status'] === 'upcoming') { $currentRound = $r; break; }
            }
        }
        if ($currentRound === null && $rounds !== []) {
            $currentRound = $rounds[array_key_last($rounds)];
        }

        $completedRounds = count(array_filter($rounds, fn ($r) => $r['status'] === 'completed'));

        // ── Top 5 (ẩn danh, trừ chính người đang xem) ───────────────────────
        $topFive = [];
        $index = 0;
        foreach (($topEntries ?? collect())->take(5) as $entry) {
            $isViewer = $viewer !== null && (int) $entry->user_id === (int) $viewer->id;
            $topFive[] = [
                'rank' => $entry->rank ?? ($index + 1),
                // Ẩn danh giống bảng xếp hạng công khai — không có cột "đồng ý hiện tên" nên
                // mặc định là bảo vệ dữ liệu học viên (đa số là trẻ dưới 18 tuổi).
                'name' => $isViewer ? ($entry->user->name ?? 'Bạn') : 'Học viên đã xác thực',
                'isViewer' => $isViewer,
                'note' => $entry->computed_at?->format('d/m/Y') ?? '',
                'score' => (float) $entry->score,
                'avatar' => asset('assets/rank-avatar-'.(($index % 5) + 1).'.png'),
            ];
            $index++;
        }

        // ── Nút hành động chính ────────────────────────────────────────────
        $joinRound = null;
        foreach ($rounds as $r) {
            if ($r['canJoin']) { $joinRound = $r; break; }
        }

        /*
         * SỬA 19/9 — 3 bước "Gửi đăng ký → BTC duyệt → Vào phòng" của bản mẫu giờ là luồng THẬT.
         * Thứ tự xét cố ý như sau, đi từ việc người dùng cần làm TIẾP THEO:
         *   chưa đăng nhập      -> mời đăng nhập
         *   chưa gửi đơn/bị từ chối -> mời gửi đăng ký (bị từ chối thì xin lại được)
         *   đang chờ duyệt      -> nút khoá, nói rõ đang chờ
         *   đã duyệt + có vòng mở -> vào phòng thi
         *   đã duyệt, chưa tới giờ -> vào KHÔNG GIAN THI để xem lịch các vòng
         */
        $isApproved = $this->isApprovedRegistration($registrationStatus);
        $isPending = $registrationStatus === CompetitionRegistration::STATUS_PENDING;

        if ($viewer === null) {
            $cta = ['label' => 'Đăng nhập để vào thi', 'href' => route('login'), 'icon' => 'shield-check', 'tone' => 'primary'];
        } elseif ($isStudent && $isPending) {
            $cta = ['label' => 'Đã gửi · Chờ BTC duyệt', 'href' => null, 'icon' => 'clock', 'tone' => 'waiting'];
        } elseif ($isStudent && ! $isApproved && $statusValue !== 'archived') {
            $cta = ['label' => 'Đăng ký tham gia', 'href' => null, 'icon' => 'file-check-2', 'tone' => 'register'];
        } elseif ($joinRound !== null) {
            // SỬA 19/9 (2) — phòng thi RIÊNG của cuộc thi, định danh bằng id VÒNG THI.
            $cta = ['label' => 'Vào phòng thi', 'href' => route('student.competitions.exam', ['competition' => $c->id, 'exam' => $joinRound['id']]), 'icon' => 'play', 'tone' => 'go'];
        } elseif ($isStudent && $isApproved) {
            $cta = ['label' => 'Vào không gian thi', 'href' => route('student.competitions.room', $c->id), 'icon' => 'trophy', 'tone' => 'go'];
        } elseif ($hasSubmitted || $statusValue === 'published') {
            $cta = ['label' => 'Xem bảng xếp hạng', 'href' => route('leaderboard.index', ['competition' => $c->id]), 'icon' => 'bar-chart-3', 'tone' => 'primary'];
        } else {
            $cta = ['label' => 'Xem thể lệ cuộc thi', 'href' => route('competitions.show', $c->id), 'icon' => 'info', 'tone' => 'muted'];
        }

        /*
         * SỬA 19/9 — nhãn ở chân thẻ giờ có thêm bước "chờ BTC duyệt" (trước đây chỉ 2 trạng
         * thái vì chưa có luồng duyệt). Thứ tự xét đi từ trạng thái "xa" nhất về gần:
         * đã nộp bài > đang chờ duyệt > đã duyệt (có vòng mở) > còn lại.
         */
        if ($hasSubmitted) {
            $participationLabel = 'Đã tham gia';
            $participationStyle = 'text-emerald-700 bg-emerald-50 border-emerald-200';
            $cardStyle = 'border-emerald-300 bg-emerald-50/70 shadow-[0_4px_18px_rgba(55,125,95,0.12)]';
        } elseif ($isApproved && $registrationStatus !== null) {
            /*
             * SỬA 19/9 (12) (khách: "đăng ký rồi admin duyệt rồi mà trạng thái vẫn Chưa tham gia")
             * — LỖI CŨ: chỉ có 3 nhánh (đã nộp bài / đang chờ duyệt / có vòng đang mở), nên
             * người ĐÃ ĐƯỢC DUYỆT mà chưa tới giờ thi rơi hết xuống nhánh cuối "Chưa tham gia"
             * — đúng lúc họ cần thấy nhất là mình đã được nhận.
             *
             * Điều kiện $registrationStatus !== null là cố ý: isApprovedRegistration() trả TRUE
             * khi máy chủ chưa chạy migration (để không khoá hệ thống cũ), nếu thiếu vế này thì
             * mọi khách vãng lai cũng hiện "Đã được duyệt".
             */
            $participationLabel = 'Đã được duyệt';
            $participationStyle = 'text-emerald-700 bg-emerald-50 border-emerald-200';
            $cardStyle = 'border-emerald-300 bg-emerald-50/70 shadow-[0_4px_18px_rgba(55,125,95,0.12)]';
        } elseif ($isPending) {
            $participationLabel = 'Đang chờ BTC duyệt';
            $participationStyle = 'text-amber-700 bg-amber-50 border-amber-200';
            $cardStyle = 'border-amber-300 bg-amber-50/70 shadow-[0_4px_18px_rgba(180,130,30,0.10)]';
        } elseif ($joinRound !== null) {
            $participationLabel = 'Đang mở cho bạn';
            $participationStyle = 'text-amber-700 bg-amber-50 border-amber-200';
            $cardStyle = 'border-amber-300 bg-amber-50/70 shadow-[0_4px_18px_rgba(180,130,30,0.10)]';
        } else {
            $participationLabel = $viewer === null ? 'Chưa đăng nhập' : 'Chưa tham gia';
            $participationStyle = 'text-slate-500 bg-slate-50 border-slate-200';
            $cardStyle = 'border-sky-100 bg-white shadow-[0_2px_12px_rgba(0,100,220,0.06)]';
        }

        $durationMinutes = $currentRound['durationMinutes'] ?? null;

        return [
            'id' => $c->id,
            'title' => $c->title,
            'type' => $c->type->value,
            'typeLabel' => $isSurvey ? 'Khảo sát' : 'Cuộc thi',
            'statusValue' => $statusValue,
            'statusLabel' => $meta['label'],
            'statusStyle' => $meta['style'],
            // Không có cột ảnh cho cuộc thi — dùng đúng bộ ảnh của source, chia đều theo id để
            // mỗi cuộc thi luôn ra cùng một ảnh (không đổi mỗi lần tải trang).
            'image' => asset('assets/contest-img-'.(($c->id % 3) + 1).'.png'),
            // Bản mẫu ghi "🏆 Đấu trường cấp Tỉnh" (dữ liệu giả) — ở đây lấy đơn vị tổ chức thật.
            'tag' => $c->isExternallyOrganized() && $c->organizer_name
                ? '🤝 '.Str::limit($c->organizer_name, 28)
                : ($isSurvey ? '📊 Khảo sát năng lực' : '🏆 Do Ôn Thi 360 tổ chức'),
            'editionLabel' => $c->starts_at !== null ? 'Mùa '.$c->starts_at->format('Y') : 'Chưa xếp lịch',
            'roundLabel' => count($rounds) > 1
                ? count($rounds).' vòng thi'
                : ($currentRound['label'] ?? 'Chưa gắn đề'),
            'duration' => $durationMinutes !== null ? $durationMinutes.' phút' : 'Không giới hạn',
            'problemsCount' => $currentRound['problemsCount'] ?? 0,
            /*
             * SỬA 19/9 (12) (khách: "số người không tăng lên, vẫn giữ nguyên như cũ") — LỖI CŨ:
             * con số này chỉ đếm leaderboard_entries, tức là người ĐÃ CÓ KẾT QUẢ ĐƯỢC CHẤM.
             * Học sinh đăng ký và được duyệt xong thì chưa thi nên chưa có dòng xếp hạng nào ->
             * số đứng yên, đúng như khách thấy.
             *
             * Lấy số LỚN HƠN giữa hai nguồn:
             *   · số thí sinh đã được ban tổ chức duyệt (nguồn mới, đúng nghĩa "đã tham gia");
             *   · số dòng xếp hạng (nguồn cũ) — giữ lại để cuộc thi TẠO TRƯỚC khi có tính năng
             *     đăng ký, vốn không có đơn nào, không bị tụt về 0 người.
             */
            'participants' => max((int) $c->leaderboard_entries_count, $approvedRegistrations),
            // Ô "Giải thưởng" của bản mẫu không có cột tương ứng; thay bằng mốc công bố kết
            // quả — thông tin thật mà người thi quan tâm đúng ở vị trí đó.
            'awardLabel' => $c->publish_result_at !== null
                ? 'Công bố kết quả '.$c->publish_result_at->format('H:i d/m/Y')
                : 'Kết quả công bố ngay khi kết thúc',
            'deadlineLabel' => $c->ends_at !== null ? $c->ends_at->format('H:i d/m/Y') : 'Không giới hạn',
            'startsAtLabel' => $c->starts_at !== null ? $c->starts_at->format('H:i d/m/Y') : 'Chưa xếp lịch',
            'startsAtTimestamp' => $c->starts_at?->getTimestamp(),
            'endsAtTimestamp' => $c->ends_at?->getTimestamp(),
            'organizerLabel' => $c->isExternallyOrganized()
                ? ($c->organizer_name ?: 'Đơn vị ngoài')
                : 'Ôn Thi 360',
            'rounds' => $rounds,
            'currentRound' => $currentRound,
            'completedRounds' => $completedRounds,
            'topFive' => $topFive,
            'participated' => $hasSubmitted,
            'participationLabel' => $participationLabel,
            'participationStyle' => $participationStyle,
            'cardStyle' => $cardStyle,
            'cta' => $cta,
            'canJoinNow' => $joinRound !== null,
            'href' => route('competitions.show', $c->id),
            'leaderboardHref' => route('leaderboard.index', ['competition' => $c->id]),

            /*
             * SỬA 19/9 — dữ liệu cho widget "Đăng ký tham gia" 3 bước trong hộp chi tiết
             * (bản mẫu: ContestsPage.jsx > RegistrationStatus). Tính sẵn ở đây thay vì để
             * view tự suy luận, vì đúng bộ luật này còn được dùng ở trang competitions.show
             * và ở AttemptService — để một chỗ tính thì 3 nơi không lệch nhau được.
             */
            'registrationStatus' => $registrationStatus,
            // CHÚ Ý: dùng so sánh TƯỜNG MINH chứ không dùng $isApproved. $isApproved cố ý trả
            // true khi máy chủ chưa migrate (để không khoá hệ thống cũ) — nhưng widget 3 bước
            // thì phải nói đúng sự thật "đã được duyệt hay chưa", không được tô xanh bước 2
            // cho người chưa hề gửi đơn.
            'registrationApproved' => $registrationStatus === CompetitionRegistration::STATUS_APPROVED,
            'registrationPending' => $isPending,
            'registrationRejected' => $registrationStatus === CompetitionRegistration::STATUS_REJECTED,
            // Bảng chưa migrate -> supported() = false -> không hiện nút gửi đơn (tránh bấm vào lỗi 500),
            // và isApprovedRegistration() trả true nên mọi thứ chạy y như trước khi có tính năng này.
            'canRequestJoin' => $isStudent
                && CompetitionRegistration::supported()
                && ! $isPending
                && $registrationStatus !== CompetitionRegistration::STATUS_APPROVED
                && $statusValue !== 'archived',
            'requestJoinUrl' => route('student.competitions.requestJoin', $c->id),
            'roomUrl' => route('student.competitions.room', $c->id),
        ];
    }

    /**
     * competitions.show — chi tiết cuộc thi thật + đề tham chiếu + đơn vị tổ chức/cố vấn
     * (note họp 13/8, mục 1) + CTA theo trạng thái/vai trò.
     */
    public function showData(int $competitionId, ?User $viewer): array
    {
        $competition = $this->competitions->query()
            ->with(['assessment', 'advisors', 'examSittings.assessment'])
            ->withCount('leaderboardEntries')
            ->findOrFail($competitionId);

        // Dùng computedStatus() (tự tính theo giờ hiện tại) thay vì cột status lưu sẵn — luôn
        // đúng dù cuộc thi lâu chưa ai vào admin sửa để cột được ghi lại (11.1).
        $computedStatusValue = $competition->computedStatus()->value;
        $meta = self::STATUS_META[$computedStatusValue] ?? ['label' => $computedStatusValue, 'tone' => 'neutral'];

        // SỬA 18/8 (yêu cầu "mỗi học sinh chỉ được làm 1 lần"): đã nộp ít nhất 1 lần cho ĐÚNG
        // CUỘC THI này (đường tham chiếu đơn, không kỳ thi con) thì không cho vào làm lại nữa —
        // nút chuyển thành "Đã làm" thay vì "Vào thi ngay".
        // SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi"): trước đây
        // đếm theo assessment_id TOÀN CỤC (countSubmittedForUserAndAssessment()) — nếu đề này
        // được dùng lại ở cuộc thi khác, học sinh nộp bên đó cũng bị coi là "đã làm" ở đây. Giờ
        // dùng hasSubmittedAttemptForCompetition() — đếm CHỈ TRONG PHẠM VI competition_id này
        // (Attempt::competition_id, ghi lúc AttemptService::startOrResume() tạo attempt), khớp
        // đúng cách assertResubmissionAllowed() chặn thật ở server.
        $alreadyAttemptedSingle = $this->hasSubmittedAttemptForCompetition($viewer, $competition->id);

        /*
         * SỬA 19/9 (khách: "click đăng ký tham gia thì admin sẽ duyệt, duyệt xong học sinh mới
         * vào được không gian thi") — trạng thái đơn đăng ký của CHÍNH người đang xem.
         * Dùng lại registrationStatuses() (hàm nhiều-cuộc-thi) với đúng 1 id thay vì viết
         * truy vấn thứ hai, để luật "chưa chạy migration thì trả rỗng" chỉ nằm ở 1 chỗ.
         */
        $registrationStatus = $this->registrationStatuses($viewer, [$competition->id])[$competition->id] ?? null;
        $isRegistrationApproved = $this->isApprovedRegistration($registrationStatus);

        // SỬA 19/9 (12) — số thí sinh ĐÃ ĐƯỢC DUYỆT, để trang chi tiết cũng trả lời được câu
        // "bao nhiêu người tham gia" chứ không chỉ có "bao nhiêu người đã lên bảng xếp hạng".
        $approvedRegistrations = CompetitionRegistration::supported()
            ? CompetitionRegistration::query()
                ->where('competition_id', $competition->id)
                ->where('status', CompetitionRegistration::STATUS_APPROVED)
                ->count()
            : 0;

        /*
         * SỬA 19/9 (2) — phòng thi cuộc thi định danh bằng id VÒNG THI, trong khi nút "Vào thi
         * ngay" ở khối dưới lại đi theo $competition->assessment_id (đường tham chiếu đơn, có
         * từ trước khi hệ thống có khái niệm nhiều vòng). Tìm đúng vòng đang trỏ tới đề đó để
         * dựng được đường dẫn mới. Cuộc thi cũ đã được migration backfill 1 vòng tương ứng nên
         * bình thường luôn tìm thấy; không thấy thì view tự lùi về màn làm bài chung (giữ
         * nguyên hành vi cũ, không để nút chết).
         */
        $directExam = $competition->assessment_id === null
            ? null
            : $competition->examSittings->firstWhere('assessment_id', $competition->assessment_id);

        return [
            'competition' => $competition,
            // Dữ liệu cho widget "Đăng ký tham gia" 3 bước ở view (Gửi đăng ký → BTC duyệt → Vào phòng).
            'registrationStatus' => $registrationStatus,
            'approvedRegistrations' => $approvedRegistrations,
            'directExamId' => $directExam?->id,
            'registrationApproved' => $isRegistrationApproved,
            'registrationPending' => $registrationStatus === CompetitionRegistration::STATUS_PENDING,
            'registrationRejected' => $registrationStatus === CompetitionRegistration::STATUS_REJECTED,
            'canRequestJoin' => $viewer !== null
                && $viewer->hasRole(Role::STUDENT)
                && CompetitionRegistration::supported()
                && ! in_array($registrationStatus, [CompetitionRegistration::STATUS_PENDING, CompetitionRegistration::STATUS_APPROVED], true)
                && $computedStatusValue !== 'archived',
            'statusLabel' => $meta['label'],
            'statusTone' => $meta['tone'],
            'rankingRule' => $competition->ranking_rule ?? [],
            'startCountdown' => $this->startCountdown($competition->starts_at),
            /*
             * "Vào thi" ở đây = làm đề tham chiếu thật (11.1: "cuộc thi chỉ tham chiếu đề để
             * tổ chức sự kiện") qua hạ tầng student.assessment.take sẵn có. Việc TỰ ĐỘNG ghi
             * nhận lượt làm bài này vào leaderboard_entries chưa được nối (cần sửa
             * AttemptService để biết attempt thuộc cuộc thi nào) — đây là phạm vi riêng, rộng
             * hơn việc dựng trang khám phá/chi tiết cuộc thi lần này nên chưa làm ở đây.
             *
             * status=ongoing hiện do Admin tự tay chuyển (không có job/scheduler tự đồng bộ
             * theo starts_at/ends_at) — thêm $isWithinWindow() làm lớp phòng vệ thứ 2: nếu
             * Admin quên chuyển trạng thái đúng lúc (quên mở, hoặc quên đóng sau khi hết
             * giờ), nút "Vào thi" vẫn không hiện sai ngoài khung thời gian thật. Cuộc thi
             * chưa đặt starts_at/ends_at (null) thì không bị chặn thêm — giữ đúng hành vi cũ
             * (chỉ dựa vào status) để không phá cuộc thi đã tạo trước khi có luật này.
             */
            // SỬA 19/9: thêm điều kiện đã-được-duyệt. PHẢI khớp AttemptService::
            // competitionEntryDecision() — hai nơi này luôn đi cùng nhau.
            'canJoinDirectly' => $viewer !== null
                && $viewer->hasRole(Role::STUDENT)
                && $competition->assessment_id !== null
                && $computedStatusValue === 'ongoing'
                && $this->isWithinWindow($competition)
                && $isRegistrationApproved
                && ! $alreadyAttemptedSingle,
            'alreadyAttempted' => $alreadyAttemptedSingle,
            /*
             * examSittings (App\Models\CompetitionExam) — 1 cuộc thi có thể gồm NHIỀU kỳ thi
             * (vd Vòng 1/Vòng 2), mỗi kỳ tham chiếu 1 đề riêng + có CTA/bảng xếp hạng riêng,
             * VÀ MỖI KỲ TỰ CÓ khung giờ starts_at/ends_at RIÊNG (App\Models\CompetitionExam::
             * computedStatus() — độc lập hoàn toàn, không phụ thuộc $competition->starts_at/
             * ends_at ở trên). Cuộc thi cũ (tạo trước tính năng này) đã được backfill 1 kỳ thi
             * tương ứng với assessment_id cũ (xem migration create_competition_exams_table)
             * nên luôn có ít nhất 1 phần tử nếu Competition từng gắn đề — không cần fallback
             * UI riêng ở view.
             */
            'examSittings' => $competition->examSittings->map(function (CompetitionExam $exam) use ($viewer, $isRegistrationApproved) {
                $examStatusValue = $exam->computedStatus();
                $examMeta = self::EXAM_STATUS_META[$examStatusValue] ?? ['label' => $examStatusValue, 'tone' => 'neutral'];

                // SỬA 18/8 (2) + SỬA 19/8: xem giải thích ở $alreadyAttemptedSingle phía trên —
                // áp dụng y hệt cho từng kỳ thi con, nhưng đếm theo competition_exam_id riêng
                // của kỳ thi này (hasSubmittedAttemptForCompetitionExam()) thay vì assessment_id
                // toàn cục, để đề dùng lại ở kỳ thi khác không bị chặn chéo.
                $examAlreadyAttempted = $this->hasSubmittedAttemptForCompetitionExam($viewer, $exam->id);

                return [
                    'id' => $exam->id,
                    'title' => $exam->displayTitle(),
                    'assessmentId' => $exam->assessment_id,
                    'startsAt' => $exam->starts_at,
                    'endsAt' => $exam->ends_at,
                    'ongoing' => $examStatusValue === 'ongoing',
                    'hasEnded' => $examStatusValue === 'ended',
                    'statusLabel' => $examMeta['label'],
                    'statusTone' => $examMeta['tone'],
                    'alreadyAttempted' => $examAlreadyAttempted,
                    /*
                     * SỬA 24/8 (v6, khách chốt sau khi test thật "Cuộc thi B" 2 vòng): BỎ HẲN
                     * mọi điều kiện dựa theo trạng thái CẤP CUỘC THI (Archived/Published) khỏi
                     * CTA của từng kỳ thi con — chỉ còn xét $examStatusValue (giờ giấc RIÊNG
                     * của đúng kỳ thi này). Trước đây (SỬA 18/8 + SỬA 19/8 (4)) có chặn thêm
                     * khi cuộc thi cha = Lưu trữ/Đã công bố — nhưng thực tế gây đúng lỗi khách
                     * báo: cuộc thi nhiều vòng, Vòng 1 đã làm xong + cuộc thi cha (có đặt
                     * starts_at/ends_at riêng ở cấp cuộc thi) đã qua giờ kết thúc CỦA CHÍNH
                     * CUỘC THI → computedStatus() cấp cuộc thi nhảy sang "Đã công bố", khiến
                     * TOÀN BỘ kỳ thi con còn lại (kể cả Vòng 2 đang thật sự "Đang diễn ra" theo
                     * đúng giờ riêng của nó) bị khoá theo, học sinh bấm vào chỉ thấy "Về trang
                     * của tôi" dù badge vẫn hiện đúng "Đang diễn ra" — đúng NGƯỢC với mục đích
                     * thiết kế nhiều vòng (mỗi vòng có khung giờ ĐỘC LẬP). Khách chốt: kỳ thi
                     * con tự chủ hoàn toàn theo giờ riêng của nó, không ăn theo trạng thái cuộc
                     * thi cha nữa — muốn khoá 1 kỳ thi cụ thể thì sửa ends_at của ĐÚNG kỳ thi
                     * đó, không dùng nút "Lưu trữ"/thời hạn cấp cuộc thi để khoá gián tiếp nữa.
                     * Khớp đúng gate mới ở server (AttemptService::competitionEntryDecision(),
                     * SỬA 24/8 (v6)) — 2 nơi PHẢI luôn khớp nhau.
                     *
                     * SỬA 18/8 (2): !$examAlreadyAttempted — mỗi học sinh chỉ được làm 1 kỳ thi
                     * con này 1 lần, đã nộp rồi thì không hiện "Vào thi" nữa (view sẽ tự chuyển
                     * sang nhánh "Đã làm").
                     */
                    // SỬA 19/9: kỳ thi con cũng phải được BTC duyệt đơn mới vào được.
                    'canJoinDirectly' => $viewer !== null
                        && $viewer->hasRole(Role::STUDENT)
                        && $exam->assessment_id !== null
                        && $examStatusValue === 'ongoing'
                        && $isRegistrationApproved
                        && ! $examAlreadyAttempted,
                ];
            })->all(),
        ];
    }

    /**
     * Trang chủ (PUB-01/02, 12.1) — "Cuộc thi sắp tới": CHỈ cuộc thi thật sự CHƯA bắt đầu,
     * xếp gần nhất trước (starts_at TĂNG dần) — khác competitions.index vốn liệt kê MỌI
     * trạng thái, mới TẠO trước (starts_at GIẢM dần qua withLeaderboardCounts()). Không dùng
     * withLeaderboardCounts() ở đây vì cuộc thi sắp diễn ra chưa có lượt tham gia nào để đếm.
     *
     * Lọc thẳng theo starts_at (chưa null và > now()) thay vì cột status='upcoming' lưu sẵn —
     * cột đó chỉ được ghi lại mỗi lần admin lưu cuộc thi (xem CompetitionService::store()/
     * update() ở tầng Admin), nên có thể "trễ" (còn ghi 'upcoming' dù giờ đã qua) nếu lâu
     * không ai vào sửa. Lọc theo ngày giờ thật thì luôn đúng ngay tại thời điểm truy vấn.
     */
    public function upcomingData(int $limit = 4): array
    {
        $competitions = $this->competitions->query()
            ->where('status', '!=', CompetitionStatus::Archived->value)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        return $competitions->map(fn (Competition $c) => $this->mapCard($c))->all();
    }

    /** now() có nằm trong [starts_at, ends_at] không — cột nào null thì bỏ qua điều kiện đó (không chặn thêm khi chưa đặt lịch cụ thể). */
    private function isWithinWindow(Competition $competition): bool
    {
        if ($competition->starts_at !== null && now()->lt($competition->starts_at)) {
            return false;
        }

        if ($competition->ends_at !== null && now()->gt($competition->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi", báo cáo thật của
     * Admin khi test): trước đây đếm theo assessment_id TOÀN CỤC — nếu 1 đề dùng lại ở nhiều
     * cuộc thi, làm cuộc thi A xong sẽ hiện "Đã làm" luôn ở cuộc thi B dù độc lập hoàn toàn.
     * Giờ đếm CHỈ TRONG PHẠM VI đúng cuộc thi này (competition_id — Attempt::competition_id,
     * ghi lúc AttemptService::startOrResume() tạo attempt), khớp đúng cách
     * assertResubmissionAllowed() chặn thật ở server cho trường hợp cuộc thi tham chiếu đề
     * TRỰC TIẾP (không qua kỳ thi con).
     */
    private function hasSubmittedAttemptForCompetition(?User $viewer, int $competitionId): bool
    {
        if ($viewer === null) {
            return false;
        }

        return $this->attempts->countSubmittedForUserAndCompetition($viewer->id, $competitionId) > 0;
    }

    /** Tương tự hasSubmittedAttemptForCompetition() nhưng ở cấp kỳ thi con (CompetitionExam::id). */
    private function hasSubmittedAttemptForCompetitionExam(?User $viewer, int $competitionExamId): bool
    {
        if ($viewer === null) {
            return false;
        }

        return $this->attempts->countSubmittedForUserAndCompetitionExam($viewer->id, $competitionExamId) > 0;
    }

    /**
     * "Còn bao lâu nữa bắt đầu" hiển thị cho người xem — trả về NULL nghĩa là ẨN HẲN phần
     * đếm ngược (đã tới/qua giờ bắt đầu rồi, hoặc chưa đặt lịch cụ thể thì không có gì để
     * đếm) — không hiện "0 ngày" gây hiểu lầm là sắp có gì đó xảy ra "trong 0 ngày nữa".
     * Còn >= 1 ngày trọn thì trả về {days}; dưới 1 ngày thì trả về {hours, minutes} (cả giờ
     * lẫn phút) để vẫn chính xác thay vì làm tròn thô về 1 đơn vị. Carbon 3 (Laravel 13) trả
     * diffInMinutes() dạng số thực (vd 123.9xxx) — ép (int) trước khi chia để lấy số nguyên
     * giờ/phút, tránh PHP deprecation "implicit conversion from float to int loses precision".
     *
     * @return array{unit: 'days', days: int}|array{unit: 'hm', hours: int, minutes: int}|null
     */
    private function startCountdown(?Carbon $startsAt): ?array
    {
        if ($startsAt === null) {
            return null;
        }

        $totalMinutes = (int) now()->diffInMinutes($startsAt, false);

        if ($totalMinutes <= 0) {
            // Đã tới/qua giờ bắt đầu — ẩn đếm ngược, không hiện "0 ngày".
            return null;
        }

        $days = intdiv($totalMinutes, 1440);

        if ($days >= 1) {
            return ['unit' => 'days', 'days' => $days];
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        // Tối thiểu 1 phút để không hiện "0 giờ 0 phút" khi thật ra vẫn còn vài giây.
        if ($hours === 0 && $minutes === 0) {
            $minutes = 1;
        }

        return ['unit' => 'hm', 'hours' => $hours, 'minutes' => $minutes];
    }

    private function mapCard(Competition $c): array
    {
        $statusValue = $c->computedStatus()->value;
        $meta = self::STATUS_META[$statusValue] ?? ['label' => $statusValue, 'tone' => 'neutral'];

        return [
            'id' => $c->id,
            'title' => $c->title,
            'typeLabel' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
            'statusLabel' => $meta['label'],
            'statusTone' => $meta['tone'],
            'startsAt' => $c->starts_at,
            'endsAt' => $c->ends_at,
            'startCountdown' => $this->startCountdown($c->starts_at),
            'participants' => $c->leaderboard_entries_count,
        ];
    }
}

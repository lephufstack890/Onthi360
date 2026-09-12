<?php

namespace App\Services\Public;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\CompetitionExam;
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

        $cards = $competitions->map(fn (Competition $c) => $this->mapContestCard(
            $c,
            $examStats,
            $topByCompetition->get($c->id),
            $viewer,
            $isStudent,
            in_array($c->id, $submittedCompetitionIds, true),
            $submittedExamIds,
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
                'canJoin' => $isStudent
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

        if ($viewer === null) {
            $cta = ['label' => 'Đăng nhập để vào thi', 'href' => route('login'), 'icon' => 'shield-check', 'tone' => 'primary'];
        } elseif ($joinRound !== null) {
            $cta = ['label' => 'Vào phòng thi', 'href' => route('student.assessment.take', $joinRound['assessmentId']), 'icon' => 'play', 'tone' => 'go'];
        } elseif ($hasSubmitted || $statusValue === 'published') {
            $cta = ['label' => 'Xem bảng xếp hạng', 'href' => route('leaderboard.index', ['competition' => $c->id]), 'icon' => 'bar-chart-3', 'tone' => 'primary'];
        } else {
            $cta = ['label' => 'Xem thể lệ cuộc thi', 'href' => route('competitions.show', $c->id), 'icon' => 'info', 'tone' => 'muted'];
        }

        // Nhãn "đã/chưa tham gia" ở chân thẻ — chỉ 2 trạng thái thật (không có bước BTC duyệt).
        if ($hasSubmitted) {
            $participationLabel = 'Đã tham gia';
            $participationStyle = 'text-emerald-700 bg-emerald-50 border-emerald-200';
            $cardStyle = 'border-emerald-300 bg-emerald-50/70 shadow-[0_4px_18px_rgba(55,125,95,0.12)]';
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
            'participants' => (int) $c->leaderboard_entries_count,
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

        return [
            'competition' => $competition,
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
            'canJoinDirectly' => $viewer !== null
                && $viewer->hasRole(Role::STUDENT)
                && $competition->assessment_id !== null
                && $computedStatusValue === 'ongoing'
                && $this->isWithinWindow($competition)
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
            'examSittings' => $competition->examSittings->map(function (CompetitionExam $exam) use ($viewer) {
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
                    'canJoinDirectly' => $viewer !== null
                        && $viewer->hasRole(Role::STUDENT)
                        && $exam->assessment_id !== null
                        && $examStatusValue === 'ongoing'
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

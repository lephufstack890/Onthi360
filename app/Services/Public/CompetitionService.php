<?php

namespace App\Services\Public;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use Illuminate\Support\Carbon;

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
    ) {}

    /** competitions.index — danh sách cuộc thi/khảo sát công khai. */
    public function indexData(): array
    {
        return [
            'competitions' => $this->competitions->withLeaderboardCounts(30)->map(fn ($c) => $this->mapCard($c))->all(),
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
            'examSittings' => $competition->examSittings->map(function (CompetitionExam $exam) use ($viewer, $computedStatusValue) {
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
                     * BUG SỬA 18/8: trước đây bắt buộc $computedStatusValue === 'ongoing' —
                     * tức trạng thái vòng đời của CẢ CUỘC THI (tính từ $competition->starts_at/
                     * ends_at riêng) cũng phải "Đang diễn ra" thì nút "Vào thi" mới hiện, dù kỳ
                     * thi (exam sitting) này có khung giờ HOÀN TOÀN RIÊNG. Cuộc thi tạo theo
                     * kiểu nhiều vòng thường KHÔNG điền starts_at/ends_at ở cấp cuộc thi (chỉ
                     * điền cho từng kỳ thi con) — khi đó $competition->starts_at = null nên
                     * computedStatus() luôn trả "Upcoming" (Competition::computeStatusFor():
                     * starts_at null → Upcoming), NGHĨA LÀ $computedStatusValue KHÔNG BAO GIỜ
                     * bằng 'ongoing' — nút "Vào thi" bị ẩn vĩnh viễn dù kỳ thi con đang thật sự
                     * diễn ra (badge vẫn hiện đúng "Đang diễn ra" vì lấy từ $examStatusValue
                     * riêng, chỉ có CTA bên dưới bị sai) — học sinh bấm vào chỉ thấy "Về trang
                     * của tôi". Chỉ còn chặn theo cờ "Lưu trữ" cấp cuộc thi (admin chủ động lưu
                     * trữ thì không cho vào thi nữa dù kỳ thi con tính ra vẫn "ongoing"), KHÔNG
                     * chặn thêm theo cả vòng đời Upcoming/PendingPublish/Published của cuộc thi
                     * (không liên quan tới việc kỳ thi con này có đang mở hay không).
                     *
                     * SỬA 18/8 (2): thêm !$examAlreadyAttempted — mỗi học sinh chỉ được làm 1
                     * kỳ thi con này 1 lần, đã nộp rồi thì không hiện "Vào thi" nữa (view sẽ tự
                     * chuyển sang nhánh "Đã làm").
                     */
                    'canJoinDirectly' => $viewer !== null
                        && $viewer->hasRole(Role::STUDENT)
                        && $exam->assessment_id !== null
                        && $computedStatusValue !== CompetitionStatus::Archived->value
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

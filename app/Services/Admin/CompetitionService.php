<?php

namespace App\Services\Admin;

use App\Enums\CompetitionOrganizerType;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\LeaderboardEntry;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\CompetitionExamRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.competitions.* (ADM-05, 11.1: vòng đời cuộc thi).
 */
class CompetitionService
{
    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private TeacherProfileRepositoryInterface $teacherProfiles,
        private AssessmentRepositoryInterface $assessments,
        private CompetitionExamRepositoryInterface $competitionExams,
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
    ) {}

    /**
     * Nhãn hiển thị cho từng trạng thái (11.1) — dùng chung cho indexData()/showData() thay
     * vì lặp lại match() ở nhiều nơi. KHÔNG còn dùng để dựng dropdown "Trạng thái" trong form
     * thêm/sửa nữa — trạng thái giờ TỰ TÍNH theo giờ hiện tại (xem Competition::computedStatus()),
     * chỉ còn "Lưu trữ" là admin tự bấm (nút riêng, xem archive()).
     */
    private const STATUS_LABELS = [
        'upcoming' => 'Sắp diễn ra',
        'ongoing' => 'Đang diễn ra',
        'pending_publish' => 'Chờ công bố',
        'published' => 'Đã công bố',
        'archived' => 'Lưu trữ',
    ];

    private const STATUS_TONES = [
        'upcoming' => 'info',
        'ongoing' => 'warning',
        'published' => 'success',
    ];

    /** @return array{types: array, assessmentOptions: array, organizerTypes: array, teacherOptions: array} */
    private function formOptions(): array
    {
        return [
            'types' => [CompetitionType::Contest->value => 'Cuộc thi', CompetitionType::Survey->value => 'Khảo sát'],
            // Đề thi luôn thuộc Tài liệu (11.1) — cuộc thi chỉ THAM CHIẾU, không tạo đề riêng.
            'assessmentOptions' => $this->assessments->query()->orderBy('title')->get(['id', 'title'])->all(),
            'organizerTypes' => [
                CompetitionOrganizerType::Internal->value => 'Nội bộ (nền tảng tự tổ chức)',
                CompetitionOrganizerType::External->value => 'Bên ngoài tổ chức',
            ],
            // Cố vấn/đồng hành (note họp 13/8, mục 1) chỉ chọn từ giáo viên đã được duyệt (3.3).
            'teacherOptions' => $this->teacherProfiles->approvedWithUser(200)
                ->map(fn ($tp) => ['id' => $tp->user->id, 'name' => $tp->user->name])
                ->values()->all(),
        ];
    }

    /** @return array{tabs: array, competitions: array} */
    public function indexData(): array
    {
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => true, 'count' => $this->competitions->count()],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => false, 'count' => $this->teacherProfiles->countApproved()],
        ];

        $competitions = $this->competitions->latest(50)->map(function ($c) {
            $statusValue = $c->computedStatus()->value;

            return [
                'id' => $c->id,
                'name' => $c->title,
                'type' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
                // Hiện rõ Bắt đầu/Kết thúc riêng (ngày + giờ) thay vì gộp mập mờ kiểu "18/08 -
                // 19/08/2026" — nhìn 1 cái là biết chính xác mốc nào, không phải đoán.
                'startsAtLabel' => $c->starts_at?->format('d/m/Y H:i') ?? '— Chưa đặt —',
                'endsAtLabel' => $c->ends_at?->format('d/m/Y H:i') ?? '— Chưa đặt —',
                'status' => self::STATUS_LABELS[$statusValue] ?? $statusValue,
                'tone' => self::STATUS_TONES[$statusValue] ?? 'neutral',
            ];
        })->all();

        return ['tabs' => $tabs, 'competitions' => $competitions];
    }

    /** admin.competitions.create — dữ liệu tĩnh cho form. */
    public function createFormData(): array
    {
        return $this->formOptions();
    }

    /**
     * Note họp 13/8, mục 1: cuộc thi tổ chức BỞI BÊN NGOÀI bắt buộc phải nêu rõ đơn vị tổ
     * chức + có ít nhất 1 giáo viên cố vấn/đồng hành để tăng uy tín — kiểm tra ở tầng
     * Service (không chỉ ở form) để không ai lách qua request thẳng (16 mục 3).
     *
     * @throws ValidationException
     */
    private function assertOrganizerDataValid(array $data): void
    {
        if (($data['organizer_type'] ?? CompetitionOrganizerType::Internal->value) !== CompetitionOrganizerType::External->value) {
            return;
        }

        if (trim($data['organizer_name'] ?? '') === '') {
            throw ValidationException::withMessages([
                'organizer_name' => 'Cuộc thi do bên ngoài tổ chức phải nêu rõ tên đơn vị tổ chức.',
            ]);
        }

        if (empty($data['advisor_teacher_ids'])) {
            throw ValidationException::withMessages([
                'advisor_teacher_ids' => 'Cuộc thi do bên ngoài tổ chức phải có ít nhất 1 giáo viên cố vấn/đồng hành để tăng uy tín.',
            ]);
        }
    }

    /**
     * "đúng logic" cho lịch cuộc thi: mốc sau phải >= mốc trước — chỉ kiểm khi CẢ HAI mốc
     * liên quan đều được nhập (bỏ trống 1 hoặc cả 2 mốc vẫn hợp lệ, giữ đúng hành vi cũ:
     * cuộc thi "chưa đặt lịch cụ thể", giống tinh thần isWithinWindow() ở tầng Public
     * Service — cột nào null thì không chặn thêm).
     *
     * @throws ValidationException
     */
    private function assertDatesValid(array $data): void
    {
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        $publishAt = $data['publish_result_at'] ?? null;

        if ($startsAt && $endsAt && strtotime($endsAt) < strtotime($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời điểm kết thúc phải sau (hoặc bằng) thời điểm bắt đầu.',
            ]);
        }

        if ($endsAt && $publishAt && strtotime($publishAt) < strtotime($endsAt)) {
            throw ValidationException::withMessages([
                'publish_result_at' => 'Thời điểm công bố kết quả phải sau (hoặc bằng) thời điểm kết thúc.',
            ]);
        }
    }

    /** admin.competitions.store — slug tự sinh từ tiêu đề (giống Course/Product). */
    public function store(array $data): Competition
    {
        $this->assertOrganizerDataValid($data);
        $this->assertDatesValid($data);

        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->competitions->query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        // Trạng thái TỰ TÍNH theo giờ hiện tại (không còn nhận từ dropdown do admin tự chọn) —
        // cuộc thi mới tạo chưa thể "Lưu trữ" nên archived luôn false ở đây (xem
        // Competition::computeStatusFor()).
        $status = Competition::computeStatusFor($data['starts_at'] ?? null, $data['ends_at'] ?? null, $data['publish_result_at'] ?? null);

        $competition = $this->competitions->create([
            'title' => $data['title'],
            'slug' => $slug,
            'type' => $data['type'],
            'organizer_type' => $data['organizer_type'] ?? CompetitionOrganizerType::Internal->value,
            'organizer_name' => $data['organizer_type'] === CompetitionOrganizerType::External->value ? trim($data['organizer_name']) : null,
            'assessment_id' => $data['assessment_id'] ?: null,
            'rules' => $data['rules'] ?? null,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'publish_result_at' => $data['publish_result_at'] ?: null,
            'status' => $status->value,
            'ranking_rule' => $this->buildRankingRule($data),
        ]);

        $competition->advisors()->sync(array_map('intval', $data['advisor_teacher_ids'] ?? []));

        return $competition;
    }

    /** admin.competitions.edit — cuộc thi hiện tại + option form. Slug KHÔNG cho sửa (giữ link). */
    public function editFormData(int $competitionId): array
    {
        $competition = $this->competitions->query()->with('advisors')->findOrFail($competitionId);

        return array_merge($this->formOptions(), [
            'competition' => $competition,
            'selectedAdvisorIds' => $competition->advisors->pluck('id')->all(),
        ]);
    }

    public function update(Competition $competition, array $data): Competition
    {
        $this->assertOrganizerDataValid($data);
        $this->assertDatesValid($data);

        // Trạng thái TỰ TÍNH lại theo lịch mới nhập — trừ khi cuộc thi ĐANG "Lưu trữ" thì giữ
        // nguyên (Lưu trữ là hành động thủ công riêng của admin qua archive(), sửa ngày giờ
        // không được phép tự "mở lại" 1 cuộc thi đã lưu trữ).
        $status = Competition::computeStatusFor(
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
            $data['publish_result_at'] ?? null,
            $competition->status === CompetitionStatus::Archived,
        );

        $updated = $this->competitions->update($competition, [
            'title' => $data['title'],
            'type' => $data['type'],
            'organizer_type' => $data['organizer_type'] ?? CompetitionOrganizerType::Internal->value,
            'organizer_name' => $data['organizer_type'] === CompetitionOrganizerType::External->value ? trim($data['organizer_name']) : null,
            'assessment_id' => $data['assessment_id'] ?: null,
            'rules' => $data['rules'] ?? null,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'publish_result_at' => $data['publish_result_at'] ?: null,
            'status' => $status->value,
            'ranking_rule' => $this->buildRankingRule($data),
        ]);

        $updated->advisors()->sync(array_map('intval', $data['advisor_teacher_ids'] ?? []));

        return $updated;
    }

    /**
     * admin.competitions.archive — Competition KHÔNG có deleted_at (không xóa mềm được, giống
     * Material) — "gỡ" một cuộc thi khỏi lưu hành dùng status=archived, đúng bước cuối vòng đời
     * 11.1 (Sắp diễn ra → Đang diễn ra → Chờ công bố → Đã công bố → Lưu trữ), không xóa bản ghi.
     */
    public function archive(Competition $competition): Competition
    {
        return $this->competitions->update($competition, ['status' => CompetitionStatus::Archived->value]);
    }

    /**
     * SỬA 19/8 — admin.competitions.unarchive: đảo ngược archive(). Trước đây "Lưu trữ" là
     * hành động MỘT CHIỀU tuyệt đối theo đúng chủ đích ban đầu (đã ghi rõ ở archive() — chỉ
     * "Lưu trữ" mới được phép chọn tay, còn lại đều tự tính theo giờ) — nhưng thực tế phát
     * sinh: Admin bấm nhầm nút "Lưu trữ cuộc thi" (hoặc lưu trữ để test rồi muốn mở lại) thì
     * KHÔNG CÓ đường lui nào, cuộc thi kẹt ở "Lưu trữ" vĩnh viễn dù sửa lại starts_at/ends_at
     * thế nào cũng vô ích (xem update(): $archived truyền vào computeStatusFor() lấy từ
     * $competition->status CŨ, nên 1 khi đã archived thì mọi lần update() sau đều tiếp tục ép
     * về archived, không tự "sống lại" theo giờ mới nhập — đây là nguồn gốc thắc mắc "sửa ngày
     * mà trạng thái không đổi").
     * KHÔNG có 1 "trạng thái trước khi lưu trữ" nào được lưu riêng để khôi phục — Competition
     * chỉ có 1 cột status, ghi đè archived là mất trạng thái cũ. Cách đúng để "mở lại" là TÍNH
     * LẠI trạng thái từ chính starts_at/ends_at/publish_result_at đang có, y hệt lúc save bình
     * thường (archived=false) — quay lại đúng vòng đời 11.1 tự nhiên từ đây trở đi.
     */
    public function unarchive(Competition $competition): Competition
    {
        $status = Competition::computeStatusFor(
            $competition->starts_at,
            $competition->ends_at,
            $competition->publish_result_at,
            false,
        );

        return $this->competitions->update($competition, ['status' => $status->value]);
    }

    /** @return array{competition: Competition, exams: array, assessmentOptions: array} */
    public function showData(int $competitionId): array
    {
        $competition = $this->competitions->query()
            ->withCount('leaderboardEntries')
            ->with(['assessment', 'advisors'])
            ->findOrFail($competitionId);

        return array_merge(['competition' => $competition], $this->examSittingsData($competition));
    }

    /** @return array{exams: array, assessmentOptions: array} */
    public function examSittingsData(Competition $competition): array
    {
        $exams = $this->competitionExams->forCompetition($competition->id)->map(fn (CompetitionExam $e) => [
            'id' => $e->id,
            'title' => $e->displayTitle(),
            'hasCustomTitle' => $e->title !== null,
            'assessmentId' => $e->assessment_id,
            'assessmentTitle' => $e->assessment->title ?? '— Không gắn đề —',
            'startsAt' => $e->starts_at,
            'endsAt' => $e->ends_at,
            'entriesCount' => $e->leaderboardEntries()->count(),
        ])->all();

        return [
            'exams' => $exams,
            'assessmentOptions' => $this->assessments->query()->orderBy('title')->get(['id', 'title'])->all(),
        ];
    }

    /** @return array<string, array> */
    public function examValidationRules(): array
    {
        return [
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }

    /**
     * "đúng logic" cho lịch kỳ thi — cùng quy tắc với assertDatesValid() ở cấp cuộc thi:
     * kết thúc phải >= bắt đầu, chỉ kiểm khi cả 2 mốc đều được nhập.
     *
     * @throws ValidationException
     */
    private function assertExamDatesValid(array $data): void
    {
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;

        if ($startsAt && $endsAt && strtotime($endsAt) < strtotime($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời điểm kết thúc kỳ thi phải sau (hoặc bằng) thời điểm bắt đầu.',
            ]);
        }
    }

    /** admin.competitions.exams.store — thêm 1 kỳ thi mới, order tự tăng sau kỳ thi cuối cùng. */
    public function storeExam(Competition $competition, array $data): CompetitionExam
    {
        $this->assertExamDatesValid($data);

        $nextOrder = ((int) $this->competitionExams->query()->where('competition_id', $competition->id)->max('order')) + 1;

        return $this->competitionExams->create([
            'competition_id' => $competition->id,
            'assessment_id' => $data['assessment_id'],
            'title' => trim($data['title'] ?? '') ?: null,
            'order' => $nextOrder,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
        ]);
    }

    /**
     * @throws ValidationException nếu đổi đề tham chiếu của 1 kỳ thi ĐÃ có lượt xếp hạng —
     * đổi đề lúc này sẽ làm dữ liệu đã tính (theo đề cũ) không còn khớp với đề mới, tương tự
     * lý do deleteExam() chặn xoá khi đã có dữ liệu.
     */
    public function updateExam(CompetitionExam $exam, array $data): CompetitionExam
    {
        $this->assertExamDatesValid($data);

        if ((int) $data['assessment_id'] !== $exam->assessment_id && $exam->leaderboardEntries()->exists()) {
            throw ValidationException::withMessages([
                'assessment_id' => 'Kỳ thi này đã có dữ liệu xếp hạng — không thể đổi đề tham chiếu (sẽ làm sai lệch dữ liệu đã tính).',
            ]);
        }

        return $this->competitionExams->update($exam, [
            'assessment_id' => $data['assessment_id'],
            'title' => trim($data['title'] ?? '') ?: null,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
        ]);
    }

    /**
     * admin.competitions.exams.destroy — kỳ thi đã có lượt xếp hạng riêng (scope=
     * competition_exam) thì KHÔNG cho xoá trực tiếp (mất dữ liệu đã tính mà không cảnh
     * báo) — admin cần biết trước khi xoá, giống cách Material/Assessment chặn xoá khi
     * đang có phụ thuộc.
     *
     * @throws ValidationException
     */
    public function deleteExam(CompetitionExam $exam): void
    {
        if ($exam->leaderboardEntries()->exists()) {
            throw ValidationException::withMessages([
                'exam' => 'Kỳ thi này đã có dữ liệu xếp hạng — không thể xoá trực tiếp.',
            ]);
        }

        $this->competitionExams->delete($exam);
    }

    /**
     * admin.competitions.recompute-aggregate — "Tính tổng từ các kỳ thi": cộng điểm mọi kỳ
     * thi (scope=competition_exam) theo user_id thành bảng TỔNG của cuộc thi (scope=
     * competition), thay cho việc tự bịa 1 công thức tính điểm "sống" chạy ngầm trong
     * pipeline chấm bài (Attempt/grading) — vốn nằm ngoài phạm vi lần dựng luồng khách vãng
     * lai này và rủi ro hơn nhiều. Thao tác này MINH BẠCH (admin bấm mới chạy, không tự
     * động), có thể chạy lại bất cứ lúc nào sau khi có kỳ thi mới hoặc điểm mới.
     *
     * @throws ValidationException nếu cuộc thi chưa có kỳ thi nào
     */
    public function recomputeAggregateFromExams(Competition $competition): int
    {
        $examIds = $this->competitionExams->forCompetition($competition->id)->pluck('id');

        if ($examIds->isEmpty()) {
            throw ValidationException::withMessages([
                'exams' => 'Cuộc thi chưa có kỳ thi nào để tính tổng.',
            ]);
        }

        $totals = $this->leaderboardEntries->query()
            ->where('scope', 'competition_exam')
            ->whereIn('competition_exam_id', $examIds)
            ->selectRaw('user_id, sum(score) as total_score')
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->get();

        $now = now();
        $rows = $totals->values()->map(fn ($row, int $i) => [
            'scope' => 'competition',
            'competition_id' => $competition->id,
            'competition_exam_id' => null,
            'class_room_id' => null,
            'topic' => null,
            'user_id' => $row->user_id,
            'score' => $row->total_score,
            'rank' => $i + 1,
            'tie_break' => null,
            'computed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Xoá bảng tổng cũ rồi ghi lại trong 1 transaction — 11.2: bảng tổng luôn phản ánh
        // đúng dữ liệu kỳ thi con hiện có tại thời điểm bấm, không cộng dồn lịch sử cũ.
        DB::transaction(function () use ($competition, $rows) {
            $this->leaderboardEntries->query()
                ->where('scope', 'competition')
                ->where('competition_id', $competition->id)
                ->delete();

            if ($rows !== []) {
                LeaderboardEntry::query()->insert($rows);
            }
        });

        return count($rows);
    }

    /**
     * 11.2: "Nêu công thức điểm, penalty, đồng điểm, kỳ tính..." — ranking_rule là JSON tự do
     * theo schema; ở đây expose thành 3 ô mô tả (text) thay vì bắt admin tự viết JSON tay.
     */
    private function buildRankingRule(array $data): ?array
    {
        $rule = array_filter([
            'scoring_note' => trim($data['scoring_note'] ?? ''),
            'penalty_note' => trim($data['penalty_note'] ?? ''),
            'tie_break_note' => trim($data['tie_break_note'] ?? ''),
        ]);

        return $rule === [] ? null : $rule;
    }
}

<?php

namespace App\Services\Admin;

use App\Enums\CompetitionOrganizerType;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
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
    ) {}

    /** @return array{types: array, statuses: array, assessmentOptions: array, organizerTypes: array, teacherOptions: array} */
    private function formOptions(): array
    {
        return [
            'types' => [CompetitionType::Contest->value => 'Cuộc thi', CompetitionType::Survey->value => 'Khảo sát'],
            'statuses' => [
                CompetitionStatus::Upcoming->value => 'Sắp diễn ra',
                CompetitionStatus::Ongoing->value => 'Đang diễn ra',
                CompetitionStatus::PendingPublish->value => 'Chờ công bố',
                CompetitionStatus::Published->value => 'Đã công bố',
                CompetitionStatus::Archived->value => 'Lưu trữ',
            ],
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

        $competitions = $this->competitions->latest(50)->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->title,
            'type' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
            'time' => ($c->starts_at?->format('d/m') ?? '').' - '.($c->ends_at?->format('d/m/Y') ?? ''),
            'status' => match ($c->status) {
                CompetitionStatus::Upcoming => 'Sắp diễn ra',
                CompetitionStatus::Ongoing => 'Đang diễn ra',
                CompetitionStatus::PendingPublish => 'Chờ công bố',
                CompetitionStatus::Published => 'Đã công bố',
                CompetitionStatus::Archived => 'Lưu trữ',
            },
            'tone' => match ($c->status) {
                CompetitionStatus::Upcoming => 'info',
                CompetitionStatus::Ongoing => 'warning',
                CompetitionStatus::Published => 'success',
                default => 'neutral',
            },
        ])->all();

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

    /** admin.competitions.store — slug tự sinh từ tiêu đề (giống Course/Product). */
    public function store(array $data): Competition
    {
        $this->assertOrganizerDataValid($data);

        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->competitions->query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

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
            'status' => $data['status'],
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
            'status' => $data['status'],
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

    /** @return array{competition: Competition} */
    public function showData(int $competitionId): array
    {
        $competition = $this->competitions->query()
            ->withCount('leaderboardEntries')
            ->with(['assessment', 'advisors'])
            ->findOrFail($competitionId);

        return ['competition' => $competition];
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

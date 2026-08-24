<?php

namespace App\Services\Teacher;

use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Services\Admin\CompetitionService as AdminCompetitionService;

/**
 * SỬA 24/8 — khách yêu cầu: "giáo viên chỉ được thêm kỳ thi và sửa kỳ thi trong cuộc thi,
 * không được sửa cuộc thi". Giáo viên ở đây là cố vấn/đồng hành của cuộc thi (xem
 * Competition::advisors(), gán bởi Admin lúc tạo/sửa cuộc thi ở admin.competitions.*).
 *
 * Toàn bộ logic CRUD kỳ thi (storeExam/updateExam/deleteExam/examValidationRules/showData)
 * TÁI SỬ DỤNG NGUYÊN từ Admin\CompetitionService — KHÔNG chép lại — để không bao giờ lệch quy
 * tắc (vd "kỳ thi đã có xếp hạng thì không cho xoá/đổi đề") giữa 2 nơi Admin/Teacher (đúng quy
 * ước "2 nơi phải luôn khớp nhau" đã dùng cho AttemptService/Public\CompetitionService).
 * Lớp này chỉ thêm đúng 2 việc Admin\CompetitionService chưa có: (1) lọc danh sách cuộc thi
 * theo giáo viên đang đăng nhập có phải cố vấn hay không, (2) chặn (403) nếu giáo viên thao
 * tác lên 1 cuộc thi/kỳ thi mà mình KHÔNG phải cố vấn. Controller cố tình KHÔNG có
 * store()/update()/archive() cho Competition — chỉ Admin\CompetitionController có.
 */
class CompetitionService
{
    public function __construct(
        private readonly CompetitionRepositoryInterface $competitions,
        private readonly AdminCompetitionService $adminCompetitionService,
    ) {}

    /**
     * teacher.competitions.index — chỉ liệt kê cuộc thi mà giáo viên này là cố vấn/đồng hành.
     * SỬA 24/8 (2) — khách yêu cầu bỏ hẳn cột "Trạng thái" ở danh sách này, không cần tính
     * computedStatus() nữa (khác StatusLabels/Tones vẫn còn ở Admin\CompetitionService cho
     * trang Admin, chỉ bỏ riêng ở bản Teacher).
     */
    public function indexData(int $teacherId): array
    {
        $competitions = $this->competitions->query()
            ->whereHas('advisors', fn ($q) => $q->where('users.id', $teacherId))
            ->withCount('examSittings')
            ->latest('starts_at')
            ->get()
            ->map(fn (Competition $c) => [
                'id' => $c->id,
                'name' => $c->title,
                'type' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
                'examsCount' => $c->exam_sittings_count,
            ])->all();

        return ['competitions' => $competitions];
    }

    /**
     * teacher.competitions.show — tái dùng nguyên Admin\CompetitionService::showData() (đã
     * kèm sẵn exams + assessmentOptions), chỉ thêm chặn 403 nếu không phải cố vấn.
     */
    public function showData(int $competitionId, int $teacherId): array
    {
        $data = $this->adminCompetitionService->showData($competitionId);
        $this->assertAdvisor($data['competition'], $teacherId);

        return $data;
    }

    public function examValidationRules(): array
    {
        return $this->adminCompetitionService->examValidationRules();
    }

    public function storeExam(Competition $competition, int $teacherId, array $data): CompetitionExam
    {
        $this->assertAdvisor($competition, $teacherId);

        return $this->adminCompetitionService->storeExam($competition, $data);
    }

    public function updateExam(CompetitionExam $exam, int $teacherId, array $data): CompetitionExam
    {
        $this->assertAdvisor($exam->competition, $teacherId);

        return $this->adminCompetitionService->updateExam($exam, $data);
    }

    public function deleteExam(CompetitionExam $exam, int $teacherId): void
    {
        $this->assertAdvisor($exam->competition, $teacherId);

        $this->adminCompetitionService->deleteExam($exam);
    }

    /** Chặn giáo viên thao tác lên cuộc thi/kỳ thi mà mình không phải cố vấn (403). */
    private function assertAdvisor(Competition $competition, int $teacherId): void
    {
        $isAdvisor = $competition->relationLoaded('advisors')
            ? $competition->advisors->contains('id', $teacherId)
            : $competition->advisors()->where('users.id', $teacherId)->exists();

        abort_unless($isAdvisor, 403, 'Bạn không phải giáo viên cố vấn của cuộc thi này.');
    }
}

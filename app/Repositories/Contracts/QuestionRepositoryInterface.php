<?php

namespace App\Repositories\Contracts;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface QuestionRepositoryInterface extends BaseRepositoryInterface
{

    public function byOwner(int $ownerId, ?string $status = null, int $limit = 50): Collection;

    public function countByOwner(int $ownerId, ?string $status = null): int;

    public function sharedLatestWithOwner(int $limit = 50): Collection;

    public function countShared(): int;

    public function allLatestWithOwner(int $limit = 50): Collection;

    /**
     * SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — như allLatestWithOwner() nhưng có LỌC.
     * $filters nhận các khoá (khoá vắng mặt/null = không lọc theo tiêu chí đó):
     *   'subject' => mã môn (SubjectCatalog::SUBJECTS) hoặc 'none' = chưa phân loại
     *   'grade'   => khối 6-12 hoặc 'none' = chưa gán khối
     *   'type'    => Enums\QuestionType
     *   'status'  => Enums\ContentStatus
     *   'q'       => từ khoá, khớp tên HOẶC mã câu hỏi
     *
     * SỬA 18/9 — thêm 3 khoá nữa (dùng chung cho Kho câu hỏi của Admin VÀ của Giáo viên):
     *   'difficulty' => 'easy'|'medium'|'hard'|'expert' (khớp giá trị đã đặt HOẶC suy theo
     *                   điểm khi câu chưa đặt) hoặc 'none' = chưa ai đặt độ khó.
     *                   Xem App\Support\QuestionDifficulty.
     *   'owner_id'   => chỉ lấy câu của 1 giáo viên (kho riêng)
     *   'owner_type' => 'shared' = chỉ Kho chung
     */
    public function allWithOwnerFiltered(array $filters, int $limit = 50): Collection;

    /** SỬA 8/9 (3) — tổng số câu khớp bộ lọc (để hiện "đang xem X / Y"). */
    public function countAllFiltered(array $filters): int;

    /**
     * SỬA 8/9 (3) — đếm số câu theo từng môn để hiện ngay trên bộ lọc.
     *
     * SỬA 18/9 — $scope (tuỳ chọn) nhận 'owner_id'/'owner_type' giống allWithOwnerFiltered()
     * để trang Giáo viên đếm đúng kho đang xem; bỏ trống = đếm toàn bộ (hành vi cũ của Admin).
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, int> mã môn => số câu; khoá '' là nhóm chưa phân loại
     */
    public function countsBySubject(array $scope = []): array;

    /**
     * "Luyện tập theo câu" (Giai đoạn 6) — chỉ lấy ID câu hỏi ĐÃ PHÁT HÀNH + dạng Trắc nghiệm/
     * Điền đáp án/Lập trình, lọc thêm theo $tagIds (rỗng = không lọc) và $type (null = tất cả
     * dạng). SỬA 24/8 (v2) — khách chốt: dùng CẢ câu Kho chung VÀ câu thuộc kho riêng giáo
     * viên (không lọc owner_type nữa), chỉ cần đã phát hành. SỬA 24/8 (v4) — khách chốt: thêm
     * dạng Lập trình vào luôn — hệ thống vẫn CHƯA có sandbox chấm code thật, nên câu Lập trình
     * lấy ra từ đây chỉ được GHI NHẬN bài làm ở Student\PracticeByQuestionService::answer(),
     * không tự chấm đúng/sai (giống quy ước "Queued" của AttemptService). Chỉ trả ID —
     * Student\PracticeByQuestionService tự fetch từng câu khi cần, tránh tải hết nội dung câu
     * hỏi vào session.
     */
    public function idsForPractice(?string $type, array $tagIds): array;
}

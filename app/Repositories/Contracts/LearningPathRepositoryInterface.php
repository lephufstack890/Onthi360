<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface LearningPathRepositoryInterface extends BaseRepositoryInterface
{
    /** Danh sách cho khu quản trị — kèm số bậc và các bậc, theo thứ tự đã xếp. */
    public function allForAdmin(): Collection;

    /** Một lộ trình kèm đủ các bậc, dùng cho màn xếp bậc và trang chi tiết. */
    public function findWithCourses(int $id): ?\App\Models\LearningPath;

    public function findBySlugWithCourses(string $slug): ?\App\Models\LearningPath;

    /**
     * Lộ trình đang hiển thị công khai, đã kèm các bậc.
     *
     * Dùng cho khối "Chọn mục tiêu hoặc lộ trình" ở trang chủ và trang /lo-trinh: số lượng
     * thực tế chỉ vài lộ trình nên nạp hết một lần rồi lọc tại chỗ, không truy vấn mỗi lần
     * người dùng đổi lựa chọn.
     */
    public function publishedWithCourses(): Collection;

    public function maxSortOrder(): int;

    /** Số lộ trình theo từng trạng thái, khoá là giá trị chuỗi của Enums\ContentStatus. */
    public function countsByStatus(): array;
}

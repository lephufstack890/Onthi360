<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ContactMessageRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * admin.contact-messages.index — mới nhất trước, không phân trang (số lượng thực tế nhỏ,
     * cùng quy ước với các màn quản trị khác). Có lọc theo trạng thái / loại và tìm theo tên,
     * email, mã phiếu hoặc nội dung.
     *
     * @param  array{status?: string|null, topic?: string|null, q?: string|null}  $filters
     */
    public function search(array $filters = [], int $limit = 200): Collection;

    /** Tổng số phiếu có trong bảng — dùng cho dòng "hiển thị X / Y". */
    public function countAll(): int;

    /** Số phiếu theo từng trạng thái, khoá là giá trị chuỗi của Enums\ContactMessageStatus. */
    public function countsByStatus(): array;

    /** Số phiếu gửi trong ngày hôm nay. */
    public function countToday(): int;

    /** Số phiếu chưa ai đụng tới — dùng cho viên số trên menu. */
    public function countNew(): int;
}

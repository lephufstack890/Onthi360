<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface TestimonialRepositoryInterface extends BaseRepositoryInterface
{
    /** Câu chuyện ĐANG HIỂN THỊ ở trang chủ, đã xếp đúng thứ tự admin đặt. */
    public function publishedForHome(int $limit = 3): Collection;

    /** Toàn bộ danh sách cho màn quản trị (mọi trạng thái). */
    public function allForAdmin(): Collection;

    /** Số thứ tự lớn nhất đang có — dùng để xếp câu chuyện mới xuống cuối. */
    public function maxSortOrder(): int;
}

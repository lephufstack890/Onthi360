<?php

namespace App\Repositories\Contracts;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface extends BaseRepositoryInterface
{
    public function allOrderedByName(): Collection;

    /** Trả về Tag có sẵn (khớp tên, không phân biệt hoa/thường) hoặc tạo mới nếu chưa có. */
    public function findOrCreateByName(string $name): Tag;
}

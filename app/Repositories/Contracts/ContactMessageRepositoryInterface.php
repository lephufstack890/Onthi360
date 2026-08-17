<?php

namespace App\Repositories\Contracts;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ContactMessageRepositoryInterface extends BaseRepositoryInterface
{

    /** admin.contact-messages.index — mới nhất trước, không phân trang (số lượng thực tế nhỏ, cùng quy ước với các màn admin khác). */
    public function recent(int $limit = 100): Collection;

    public function countNew(): int;
}

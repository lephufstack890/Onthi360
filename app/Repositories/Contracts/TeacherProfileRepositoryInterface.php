<?php

namespace App\Repositories\Contracts;

use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface TeacherProfileRepositoryInterface extends BaseRepositoryInterface
{

    public function pendingWithUser(): Collection;

    public function countPending(): int;

    public function approvedWithUser(int $limit = 50): Collection;

    /** Danh sách cho màn vinh danh — gồm cả hồ sơ trưng bày không gắn tài khoản. */
    public function showcaseList(int $limit = 200): Collection;

    public function countApproved(): int;

    public function findByUserId(int $userId): ?TeacherProfile;
}

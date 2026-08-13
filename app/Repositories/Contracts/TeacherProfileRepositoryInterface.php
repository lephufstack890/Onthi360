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

    public function countApproved(): int;

    public function findByUserId(int $userId): ?TeacherProfile;
}

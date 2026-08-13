<?php

namespace App\Repositories\Eloquent;

use App\Models\TeacherProfile;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TeacherProfileRepository extends EloquentRepository implements TeacherProfileRepositoryInterface
{
    protected string $modelClass = TeacherProfile::class;

    public function pendingWithUser(): Collection
    {
        return $this->query()->where('approval_status', 'pending')->with('user')->latest()->get();
    }

    public function countPending(): int
    {
        return $this->query()->where('approval_status', 'pending')->count();
    }

    public function approvedWithUser(int $limit = 50): Collection
    {
        return $this->query()->where('approval_status', 'approved')->with('user')->latest()->limit($limit)->get();
    }

    public function countApproved(): int
    {
        return $this->query()->where('approval_status', 'approved')->count();
    }

    public function findByUserId(int $userId): ?TeacherProfile
    {
        return $this->query()->where('user_id', $userId)->first();
    }
}

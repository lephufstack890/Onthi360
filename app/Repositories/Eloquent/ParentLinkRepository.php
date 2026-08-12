<?php

namespace App\Repositories\Eloquent;

use App\Models\ParentLink;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ParentLinkRepository extends EloquentRepository implements ParentLinkRepositoryInterface
{
    protected string $modelClass = ParentLink::class;

    public function verifiedForParentWithStudent(int $parentUserId): Collection
    {
        return $this->query()
            ->where('parent_user_id', $parentUserId)
            ->where('status', 'verified')
            ->with('student')
            ->get();
    }

    public function forParentWithStudent(int $parentUserId): Collection
    {
        return $this->query()->where('parent_user_id', $parentUserId)->with('student')->get();
    }

    public function findVerifiedLink(int $parentUserId, int $studentUserId): ?ParentLink
    {
        return $this->query()
            ->where('parent_user_id', $parentUserId)
            ->where('student_user_id', $studentUserId)
            ->where('status', 'verified')
            ->with('student')
            ->first();
    }
}

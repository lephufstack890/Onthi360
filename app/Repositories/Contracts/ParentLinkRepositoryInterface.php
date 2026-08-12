<?php

namespace App\Repositories\Contracts;

use App\Models\ParentLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ParentLinkRepositoryInterface extends BaseRepositoryInterface
{

    public function verifiedForParentWithStudent(int $parentUserId): Collection;

    public function forParentWithStudent(int $parentUserId): Collection;

    public function findVerifiedLink(int $parentUserId, int $studentUserId): ?ParentLink;
}

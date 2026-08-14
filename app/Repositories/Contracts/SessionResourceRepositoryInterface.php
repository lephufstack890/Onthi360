<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface SessionResourceRepositoryInterface extends BaseRepositoryInterface
{
    public function forClassSession(int $classSessionId): Collection;
}

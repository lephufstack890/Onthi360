<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AuditLogRepositoryInterface extends BaseRepositoryInterface
{

    public function latestWithActor(int $limit = 10): Collection;

    public function forAuditable(string $auditableType, int $auditableId, int $limit = 20): Collection;
}

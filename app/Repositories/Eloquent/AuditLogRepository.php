<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AuditLogRepository extends EloquentRepository implements AuditLogRepositoryInterface
{
    protected string $modelClass = AuditLog::class;

    public function latestWithActor(int $limit = 10): Collection
    {
        return $this->query()->with('actor')->latest()->limit($limit)->get();
    }

    public function forAuditable(string $auditableType, int $auditableId, int $limit = 20): Collection
    {
        return $this->query()
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->with('actor')
            ->latest()
            ->limit($limit)
            ->get();
    }
}

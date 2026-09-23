<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AuditLogRepository extends EloquentRepository implements AuditLogRepositoryInterface
{
    protected string $modelClass = AuditLog::class;

    /**
     * SỬA 23/9 (khách: "vào admin 500" — MySQL 1038 Out of sort memory) — 2 thay đổi nhỏ khiến
     * câu này nhẹ hẳn:
     *   · sắp theo (created_at, id) khớp ĐÚNG chỉ mục mới thêm (migration
     *     add_created_at_index_to_audit_logs_table) nên MySQL không phải sắp xếp nữa;
     *   · chỉ lấy các cột màn Tổng quan thật sự dùng, BỎ cột JSON 'changes' vốn rất nặng —
     *     chính cột này làm vùng nhớ sắp xếp phình to. Màn hình đó chỉ hiện hành động + thời
     *     gian + người thực hiện, xem Admin\DashboardService::buildDashboardData().
     */
    public function latestWithActor(int $limit = 10): Collection
    {
        return $this->query()
            ->select(['id', 'actor_id', 'action', 'auditable_type', 'auditable_id', 'reason', 'created_at'])
            ->with('actor:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function forAuditable(string $auditableType, int $auditableId, int $limit = 20): Collection
    {
        return $this->query()
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->with('actor:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}

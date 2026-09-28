<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAuditLog('created'));
        static::updated(fn ($model) => $model->writeAuditLog('updated'));
        static::deleted(fn ($model) => $model->writeAuditLog('deleted'));
    }

    protected function writeAuditLog(string $action): void
    {
        $changes = $action === 'updated' ? $this->getChanges() : null;

        // created()/deleted() không có "changes" hữu ích ngoài toàn bộ attributes;
        // với updated() chỉ ghi field thực sự đổi, tránh log rác.
        if ($action === 'updated' && empty($changes)) {
            return;
        }

        AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'changes' => $changes,
            'reason' => property_exists($this, 'auditReason') ? static::$auditReason : null,
        ]);
    }
}

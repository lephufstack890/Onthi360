<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Gắn vào model cần audit log theo yêu cầu 16 mục 4. Chỉ ghi log — KHÔNG chặn
 * hành động (việc chặn thuộc về Policy/Service ở tầng nghiệp vụ, ví dụ
 * AccessGateService, TeacherAttachMaterialAction).
 *
 * Cách dùng: `use Auditable;` trong model, tùy chọn set `public static
 * ?string $auditReason = null;` trước khi save() nếu muốn ghi lý do (ví dụ
 * admin từ chối đơn) — trait tự đọc thuộc tính này nếu có.
 */
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

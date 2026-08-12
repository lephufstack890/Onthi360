<?php

namespace App\Services\Access;

use App\Enums\AccessRightStatus;
use App\Models\AccessRight;
use Illuminate\Support\Carbon;

/**
 * Phân loại một AccessRight vào 1 trong 4 nhóm hiển thị ở access.myAccess
 * (ACC-07): active / expiring / expired / other. Tách riêng khỏi
 * AccessController để không lặp lại công thức ngày-giờ này ở nơi khác trong
 * domain quyền truy cập (Admin\AccessRightController có bản riêng, không cần
 * gộp — xem README refactor).
 */
class AccessRightStatusService
{
    public const string ACTIVE = 'active';

    public const string EXPIRING = 'expiring';

    public const string EXPIRED = 'expired';

    public const string OTHER = 'other';

    /** Sắp hết hạn = còn hiệu lực và hết hạn trong vòng 14 ngày tới. */
    private const int EXPIRING_WINDOW_DAYS = 14;

    public function classify(AccessRight $right, ?Carbon $now = null): string
    {
        $now = $now ?? now();

        if ($right->status !== AccessRightStatus::Active || $right->expires_at === null) {
            return $right->status === AccessRightStatus::Expired ? self::EXPIRED : self::OTHER;
        }

        if ($right->expires_at->diffInDays($now, false) >= -self::EXPIRING_WINDOW_DAYS && $right->expires_at->isFuture()) {
            return self::EXPIRING;
        }

        return $right->expires_at->isPast() ? self::EXPIRED : self::ACTIVE;
    }
}

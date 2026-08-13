<?php

namespace App\Services\Student;

use App\Models\User;
use App\Services\NotificationService as GenericNotificationService;

/**
 * STU-11 (phần thông báo). Uỷ quyền cho App\Services\NotificationService dùng chung mọi
 * vai trò (kênh 'database' của Illuminate Notifications, migration
 * 2025_01_01_000380_create_notifications_table) — hạ tầng bảng notifications đã có thật,
 * không còn là stub trả mảng rỗng nữa.
 */
class NotificationService
{
    public function __construct(private readonly GenericNotificationService $notifications) {}

    public function forUser(User $user): array
    {
        return $this->notifications->forUser($user)['items'];
    }
}

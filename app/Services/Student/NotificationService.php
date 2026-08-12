<?php

namespace App\Services\Student;

use App\Models\User;

/**
 * STU-11 (phần thông báo).
 * TODO: chưa có bảng notifications trong schema hiện tại — cần thêm migration +
 * model Notification (hoặc dùng Laravel Notifications mặc định) trước khi hiển thị
 * dữ liệu thật. Hiện trả về danh sách rỗng để UI hiển thị đúng trạng thái "chưa có
 * thông báo" thay vì dữ liệu giả.
 */
class NotificationService
{
    public function forUser(User $user): array
    {
        return [];
    }
}

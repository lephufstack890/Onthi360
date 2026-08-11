<?php

namespace App\Enums;

enum TeacherApprovalStatus: string
{
    case NotRegistered = 'not_registered';
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotRegistered => 'Chưa đăng ký',
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã được duyệt',
            self::Suspended => 'Tạm dừng',
            self::Rejected => 'Từ chối có lý do',
        };
    }
}

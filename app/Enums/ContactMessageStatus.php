<?php

namespace App\Enums;

/**
 * Trạng thái một yêu cầu hỗ trợ.
 *
 * SỬA 13/9 — thêm InProgress và Spam. Trước đây chỉ có new/resolved nên hai quản trị viên
 * cùng mở một phiếu là cùng trả lời một người, còn phiếu rác thì buộc phải đánh dấu "đã xử
 * lý" mới cho khuất mắt, làm sai luôn con số đã xử lý. Hai giá trị cũ giữ nguyên chuỗi lưu
 * trong CSDL nên dữ liệu đang có không phải chuyển đổi.
 */
enum ContactMessageStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::InProgress => 'Đang xử lý',
            self::Resolved => 'Đã xử lý',
            self::Spam => 'Rác',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Spam => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'mail',
            self::InProgress => 'clock-3',
            self::Resolved => 'check-circle-2',
            self::Spam => 'ban',
        };
    }
}

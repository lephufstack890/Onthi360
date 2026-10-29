<?php

namespace App\Enums;

enum SupportTopic: string
{
    case Learning = 'learning';
    case Technical = 'technical';
    case Payment = 'payment';
    case Account = 'account';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Learning => 'Khóa học & lộ trình',
            self::Technical => 'Lỗi kỹ thuật / chấm bài',
            self::Payment => 'Thanh toán & mã kích hoạt',
            self::Account => 'Tài khoản & đăng nhập',
            self::Other => 'Nội dung khác',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Learning => 'brand',
            self::Technical => 'danger',
            self::Payment => 'warning',
            self::Account => 'info',
            self::Other => 'neutral',
        };
    }

    /** @return array<string, string> value => nhãn tiếng Việt, dùng cho ô chọn và bộ lọc. */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

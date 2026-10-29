<?php

namespace App\Enums;

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

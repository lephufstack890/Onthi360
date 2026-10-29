<?php

namespace App\Enums;

enum PathLanguage: string
{
    case Python = 'python';
    case Cpp = 'cpp';

    public function label(): string
    {
        return match ($this) {
            self::Python => 'Python',
            self::Cpp => 'C++',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Python => 'info',
            self::Cpp => 'brand',
        };
    }

    /** @return array<string, string> value => nhãn, dùng cho ô chọn và bộ lọc. */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

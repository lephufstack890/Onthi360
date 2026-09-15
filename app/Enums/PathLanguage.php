<?php

namespace App\Enums;

/**
 * Ngôn ngữ lập trình của một lộ trình.
 *
 * Là enum chứ không phải chuỗi tự do vì đây là MỘT TRONG BA BỘ LỌC ở khối "Chọn mục tiêu
 * hoặc lộ trình" ngoài trang chủ — gõ tay "C++" / "Cpp" / "c++" lẫn lộn là bộ lọc hỏng.
 *
 * Thêm ngôn ngữ mới (Java, Pascal...) chỉ cần thêm một case ở đây; cột trong CSDL là string
 * nên không phải ALTER TABLE.
 */
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

    /** Tông màu viên nhãn, dùng chung cho cả khu quản trị lẫn trang công khai. */
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

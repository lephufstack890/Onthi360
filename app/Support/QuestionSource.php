<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * SỬA 10/10 — ô "Nguồn" của câu hỏi (questions.source_name, văn bản tự do tối đa 255 ký tự).
 * Một chỗ chung cho Admin\ContentService và Teacher\QuestionService để chuẩn hoá giống nhau.
 */
final class QuestionSource
{
    public const MAX = 255;

    private static ?bool $hasColumn = null;

    /** Gọn khoảng trắng; rỗng -> null (hiện nhãn mặc định ở trang Luyện tập). */
    public static function normalize(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $text === '' ? null : mb_substr($text, 0, self::MAX);
    }

    /**
     * Cặp thuộc tính để ghi: ['source_name' => ...]. Nếu CSDL chưa chạy migration thì trả mảng rỗng
     * để Lưu câu hỏi không vỡ trong lúc chờ `php artisan migrate`.
     *
     * @return array<string, ?string>
     */
    public static function attributes(array $data): array
    {
        self::$hasColumn ??= Schema::hasColumn('questions', 'source_name');

        return self::$hasColumn ? ['source_name' => self::normalize($data['source_name'] ?? null)] : [];
    }
}

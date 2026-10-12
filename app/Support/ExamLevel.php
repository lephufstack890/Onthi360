<?php

namespace App\Support;

/**
 * SỬA 12/10 (khách: "thêm lọc theo KHỐI nữa, mặc định THCS · THPT · Khác") — khối học mà đề dành cho, dùng cho
 * ô lọc "Khối" ở tab Đề thi luyện tập ngoài trang công khai và cho ô chọn ở form đề (admin/giáo viên).
 *
 * Lưu ở assessments.exam_level. Tách khỏi exam_category (đó là KỲ THI nào: HSG lớp 9/12, Tin học trẻ…) vì hai chiều
 * này độc lập — "Tin học trẻ" có đề THCS lẫn đề THPT. Đề chưa chọn khối (null) được tính vào "Khác" khi lọc.
 */
class ExamLevel
{
    /** Mã => nhãn. Thêm khối mới: thêm 1 dòng, không cần migration. */
    public const LEVELS = [
        'thcs' => 'THCS',
        'thpt' => 'THPT',
        'khac' => 'Khác',
    ];

    public static function label(?string $code): ?string
    {
        return $code !== null ? (self::LEVELS[$code] ?? null) : null;
    }

    /** Mã hợp lệ hoặc null (mã lạ -> null = chưa chọn khối). */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $code = mb_strtolower(trim($raw));

        return array_key_exists($code, self::LEVELS) ? $code : null;
    }

    /** Nhóm LỌC của 1 mã — đề chưa chọn khối (hoặc mã lạ) thuộc "Khác". */
    public static function filterGroup(?string $code): string
    {
        return ($code !== null && array_key_exists($code, self::LEVELS)) ? $code : 'khac';
    }

    /** Luật validate cho ô chọn ở form — sinh từ LEVELS để không bao giờ lệch danh sách. */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_keys(self::LEVELS));
    }
}

<?php

namespace App\Support;

/**
 * SỬA 11/10 (khách: "Điểm từng câu để người dùng nhập, đừng lấy điểm của độ khó") — điểm của
 * từng câu TRONG ĐỀ do người ra đề nhập (số nguyên hoặc thập phân, tối đa 2 chữ số lẻ), ghi vào
 * assessment_items.points_override. Đây là NƠI DUY NHẤT chuẩn hoá con số đó cho cả form admin
 * lẫn form giáo viên, để hai bên không lệch luật.
 */
final class AssessmentPoints
{
    public const MAX = 1000;

    /** Luật validate cho 2 ô: points (mảng theo question_id) và points.* (từng số). */
    public static function rules(): array
    {
        return [
            'points' => ['nullable', 'array'],
            'points.*' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX],
        ];
    }

    public static function attributeNames(): array
    {
        return ['points' => 'Điểm', 'points.*' => 'Điểm từng câu'];
    }

    /**
     * Điểm của 1 câu: lấy số người dùng nhập; để trống/không hợp lệ thì dùng $fallback — con số
     * gợi ý ban đầu hiện sẵn trong ô (không bao giờ ghi null, xem ghi chú SỬA 23/9 ở service).
     */
    public static function resolve(mixed $raw, float $fallback): float
    {
        if (is_string($raw)) {
            $raw = str_replace(',', '.', trim($raw));
        }

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return round(max(0.0, $fallback), 2);
        }

        return round(min(self::MAX, max(0.0, (float) $raw)), 2);
    }

    /** Cộng điểm, làm tròn 2 chữ số để tránh sai số số thực (0.1 + 0.2). */
    public static function sum(iterable $points): float
    {
        $total = 0.0;
        foreach ($points as $p) {
            $total += (float) $p;
        }

        return round($total, 2);
    }
}

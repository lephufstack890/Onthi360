<?php

namespace App\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * SỬA 24/10 — MÃ ĐỀ HOÁN VỊ của một kỳ thi (Đề 101, 102, 103 …) theo source UI mới (CONTEST_EXAM_FORMS).
 *
 * Mọi mã đề dùng CÙNG câu hỏi, CÙNG điểm, CÙNG thời gian — chỉ khác THỨ TỰ câu. Thứ tự được sinh cố định từ
 * (id kỳ thi, số mã đề) nên tải lại trang / đổi máy vẫn ra đúng một thứ tự, không cần lưu bảng riêng.
 * Bài làm và điểm vẫn ghi theo question_id như cũ → chấm điểm, bảng xếp hạng, thống kê theo câu không đổi gì;
 * cột C1…Cn ở bảng xếp hạng luôn theo thứ tự câu GỐC.
 */
final class ContestForms
{
    /** Tối đa số mã đề cho một kỳ thi (đủ dùng, tránh nhập nhầm số lớn). */
    public const MAX = 9;

    /** 1 → "101", 2 → "102" … */
    public static function code(int $no): string
    {
        return (string) (100 + max(1, $no));
    }

    public static function label(int $no): string
    {
        return 'Đề '.self::code($no);
    }

    /**
     * Thứ tự câu của mã đề $no. Mã đề 1 = thứ tự gốc.
     *
     * @template T
     *
     * @param  array<int, T>  $items  danh sách theo thứ tự gốc
     * @return array<int, T>
     */
    public static function order(array $items, int $examId, int $no): array
    {
        $items = array_values($items);

        if ($no <= 1 || count($items) < 2) {
            return $items;
        }

        $shuffled = (new Randomizer(new Mt19937(crc32('contest-form:'.$examId.':'.$no))))->shuffleArray($items);

        // Trùng thứ tự gốc (đề ít câu) thì xoay vòng để mã đề thật sự khác.
        if ($shuffled === $items) {
            $shift = ($no - 1) % count($items) ?: 1;
            $shuffled = array_merge(array_slice($items, $shift), array_slice($items, 0, $shift));
        }

        return $shuffled;
    }
}

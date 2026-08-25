<?php

namespace App\Support;

/**
 * Sinh mã (code) duy nhất từ tên gốc 1 tệp — dùng khi admin tải lên 1 tệp/gói và muốn hệ
 * thống TỰ đặt mã thay vì phải gõ tay (ví dụ: đặt tên tệp "BAI01.pdf" -> mã "BAI01").
 *
 * Dùng CHUNG cho nhiều nơi có cùng thuật toán nhưng khác PHẠM VI kiểm tra trùng mã — ví dụ
 * Admin\ContentService::deriveUniqueQuestionCode() (mã câu hỏi, duy nhất TOÀN hệ thống) và
 * materialsBulkImportFromZip() (mã bài trong Sách/Chuyên đề/Đề thi, chỉ cần duy nhất TRONG 1
 * sản phẩm — 2 quyển sách khác nhau được phép trùng mã bài). Vì phạm vi kiểm tra khác nhau,
 * nơi gọi tự truyền vào qua $exists (đóng gói đúng câu truy vấn của mình), lớp này chỉ lo phần
 * thuật toán (cắt đuôi mở rộng, giới hạn độ dài, thêm hậu tố "-2", "-3"... khi trùng).
 *
 * Gom về 1 nơi để sau này đổi thuật toán (vd đổi cách đặt hậu tố, đổi độ dài tối đa) chỉ cần
 * sửa đúng 1 chỗ, không phải sửa lặp lại ở từng service.
 */
class UniqueCodeFromFilename
{
    /**
     * @param  string  $originalFilename  Tên gốc của tệp (kèm đuôi mở rộng, vd "BAI01.pdf") — chỉ lấy phần tên, bỏ đuôi.
     * @param  callable(string): bool  $exists  Trả về true nếu mã đó đã được dùng (phạm vi kiểm tra do nơi gọi tự quyết định, ví dụ chỉ trong 1 sản phẩm hay toàn hệ thống).
     * @param  int  $maxLength  Độ dài tối đa của mã sau cùng (đã tính cả hậu tố "-NN" nếu có).
     */
    public static function generate(string $originalFilename, callable $exists, int $maxLength = 40): string
    {
        $base = trim((string) pathinfo($originalFilename, PATHINFO_FILENAME));
        $base = $base !== '' ? $base : 'IMPORT';
        $base = mb_substr($base, 0, max(1, $maxLength - 4)); // chừa chỗ cho hậu tố "-NN"

        $code = $base;
        $suffix = 1;
        while ($exists($code)) {
            $suffix++;
            $code = $base.'-'.$suffix;
        }

        return $code;
    }
}

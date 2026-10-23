<?php

namespace App\Support;

/**
 * SỬA 2/10 — LOẠI ĐỀ THI (kỳ thi mà đề mô phỏng), dùng cho dải chip lọc ở tab "Đề thi luyện
 * tập" ngoài trang công khai và cho ô chọn ở form đề bên admin/giáo viên.
 *
 * KHÁC assessments.type (App\Enums\AssessmentType: practice/assignment/exam/competition_paper):
 * 'type' nói đề này ĐƯỢC DÙNG THẾ NÀO trong hệ thống (tự luyện, bài giao, đề thi, đề cuộc thi),
 * còn cột này nói đề MÔ PHỎNG KỲ THI NÀO. Một đề "practice" vẫn có thể là đề HSG Quốc gia.
 * Gộp hai thứ vào một cột thì mất một trong hai chiều, nên tách hẳn.
 *
 * Để dạng danh sách cố định (không phải ô gõ tự do) vì đây là chiều LỌC — ô gõ tự do là mỗi
 * người gõ một kiểu và dải chip vỡ ngay, đúng lý do đã ghi ở SubjectCatalog.
 * Thêm loại mới: thêm 1 dòng vào CATEGORIES, không cần migration.
 */
class ExamCategory
{
    /**
     * Mã => nhãn hiển thị. Mã là thứ lưu xuống assessments.exam_category.
     *
     * SỬA 12/10 (khách: "chỗ lọc cái Cuộc thi mặc định là 1. HSG lớp 9 · 2. HSG lớp 12 · 3. Tin học trẻ ·
     * 4. Khác") — danh sách CHỌN ĐƯỢC (form đề + ô lọc ngoài trang Luyện tập) rút còn đúng 4 mục này.
     */
    public const CATEGORIES = [
        'hsg_lop_9' => 'HSG lớp 9',
        'hsg_lop_12' => 'HSG lớp 12',
        // SỬA 23/10 (khách: "Cuộc thi đang thiếu Tuyển sinh vào lớp 10, thêm cả admin và public") — form đề (admin + giáo
        // viên), ô lọc Cuộc thi ở trang Luyện tập và bộ lọc Bộ đề đều đọc danh sách này nên tự có mục mới.
        'tuyen_sinh_10' => 'Tuyển sinh vào lớp 10',
        'tin_hoc_tre' => 'Tin học trẻ',
        'khac' => 'Khác',
    ];

    /**
     * Mã CŨ (trước 12/10) — không còn trong danh sách chọn nhưng đề đã lưu mã này vẫn phải hiện đúng nhãn và
     * không bị xoá phân loại khi sửa đề. Mã => [nhãn, nhóm lọc tương ứng trong CATEGORIES].
     * "HSG Quốc gia" là kỳ thi lớp 11-12 nên gom vào HSG lớp 12; các mã còn lại không xác định được lớp nên
     * gom vào "Khác" (người ra đề có thể phân loại lại bất cứ lúc nào).
     */
    public const LEGACY = [
        'hsg_quoc_gia' => ['HSG Quốc gia', 'hsg_lop_12'],
        'hsg_tinh' => ['HSG Tỉnh/Thành', 'khac'],
        // SỬA 23/10 — "Chuyên Tin 10" chính là đề tuyển sinh vào lớp 10 chuyên Tin: lọc vào nhóm Tuyển sinh vào lớp 10.
        'chuyen_tin' => ['Chuyên Tin 10', 'tuyen_sinh_10'],
        'olympic' => ['Olympic Tin học', 'khac'],
        'thpt' => ['Thi thử THPT', 'khac'],
    ];

    /** Nhãn của 1 mã (kể cả mã cũ); mã lạ/rỗng -> null để nơi gọi tự hiện "Chưa phân loại". */
    public static function label(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return self::CATEGORIES[$code] ?? (self::LEGACY[$code][0] ?? null);
    }

    /**
     * Nhóm LỌC của 1 mã — luôn là một khoá trong CATEGORIES: mã mới giữ nguyên, mã cũ đổi theo LEGACY, đề chưa
     * phân loại (hoặc mã lạ) vào "Khác". Ô "Cuộc thi" ngoài trang Luyện tập lọc theo giá trị này.
     */
    public static function filterGroup(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'khac';
        }

        return array_key_exists($code, self::CATEGORIES) ? $code : (self::LEGACY[$code][1] ?? 'khac');
    }

    /**
     * Mã hợp lệ hoặc null (mã lạ -> null = chưa phân loại, KHÔNG đoán bừa). Nhận cả mã cũ để sửa đề không làm
     * mất phân loại đã lưu.
     *
     * Cắt khoảng trắng và hạ chữ thường trước khi đối chiếu: ô chọn ở form luôn gửi đúng mã,
     * nhưng cùng hàm này còn nhận dữ liệu nhập hàng loạt / gọi từ nơi khác, mà "HSG_TINH" bị
     * coi là mã lạ rồi lặng lẽ thành null thì đề mất phân loại mà không ai biết.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $code = mb_strtolower(trim($raw));

        return (array_key_exists($code, self::CATEGORIES) || array_key_exists($code, self::LEGACY)) ? $code : null;
    }

    /** Luật validate cho ô chọn ở form — sinh từ CATEGORIES để không bao giờ lệch danh sách. */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_merge(array_keys(self::CATEGORIES), array_keys(self::LEGACY)));
    }
}

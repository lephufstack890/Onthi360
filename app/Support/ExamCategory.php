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
    /** Mã => nhãn hiển thị. Mã là thứ lưu xuống assessments.exam_category. */
    public const CATEGORIES = [
        'hsg_quoc_gia' => 'HSG Quốc gia',
        'hsg_tinh' => 'HSG Tỉnh/Thành',
        'chuyen_tin' => 'Chuyên Tin 10',
        'olympic' => 'Olympic Tin học',
        'thpt' => 'Thi thử THPT',
        'tin_hoc_tre' => 'Tin học trẻ',
        'khac' => 'Khác',
    ];

    /** Nhãn của 1 mã; mã lạ/rỗng -> null để nơi gọi tự hiện "Chưa phân loại". */
    public static function label(?string $code): ?string
    {
        return $code !== null ? (self::CATEGORIES[$code] ?? null) : null;
    }

    /**
     * Mã hợp lệ hoặc null (mã lạ -> null = chưa phân loại, KHÔNG đoán bừa).
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

        return array_key_exists($code, self::CATEGORIES) ? $code : null;
    }

    /** Luật validate cho ô chọn ở form — sinh từ CATEGORIES để không bao giờ lệch danh sách. */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_keys(self::CATEGORIES));
    }
}

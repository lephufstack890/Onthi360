<?php

namespace App\Support;

/**
 * SỬA 8/9 (3) (khách: "kho câu hỏi giờ làm sao để phân loại được các môn, để một đống câu hỏi
 * như này không ổn") — DANH MỤC MÔN HỌC + KHỐI LỚP dùng chung cho Kho câu hỏi.
 *
 * Cố ý dùng danh sách CỐ ĐỊNH (khách chốt) thay vì ô gõ tự do như products.subject: ô gõ tự do
 * khiến mỗi người gõ một kiểu ("Toán" / "toan" / "Toán học") và bộ lọc theo môn vỡ ngay. Cột
 * questions.subject vì vậy lưu MÃ MÔN (khoá của SUBJECTS, vd "TOAN"), hiển thị ra nhãn qua
 * label(). Muốn thêm môn mới: thêm 1 dòng vào SUBJECTS là xong, không cần migration.
 *
 * 3 nguồn dữ liệu môn/khối, dùng chung 1 bộ chuẩn hoá ở đây:
 *   1. Admin chọn tay ở form Tạo/Sửa câu hỏi (đúng mã sẵn, không cần đoán).
 *   2. Gói ZIP OT360-QPACK — taxonomy.subject.code + taxonomy.grade_levels (fromTaxonomy()).
 *   3. Câu hỏi CŨ đã có sẵn trong kho — đoán từ tiền tố mã câu hỏi (guessFromCode(), vd
 *      "TOAN6UOC_CHUNG_001" -> TOAN/6), xem Console\Commands\BackfillQuestionSubject.
 */
class SubjectCatalog
{
    /** Mã môn => nhãn hiển thị. Mã là thứ lưu xuống DB (questions.subject). */
    public const SUBJECTS = [
        'TOAN' => 'Toán',
        'VAN' => 'Ngữ văn',
        'ANH' => 'Tiếng Anh',
        'LI' => 'Vật lí',
        'HOA' => 'Hoá học',
        'SINH' => 'Sinh học',
        'KHTN' => 'Khoa học tự nhiên',
        'SU' => 'Lịch sử',
        'DIA' => 'Địa lí',
        'TIN' => 'Tin học',
        'GDCD' => 'Giáo dục công dân',
    ];

    /** Khối lớp hỗ trợ — khớp phạm vi nội dung hiện có của hệ thống (THCS + THPT). */
    public const GRADES = [6, 7, 8, 9, 10, 11, 12];

    /**
     * Bí danh hay gặp trong gói ZIP/mã câu hỏi cũ -> mã chuẩn ở SUBJECTS. Khoá đã được chuẩn
     * hoá sẵn theo đúng cách normalize() làm (viết hoa, bỏ dấu, bỏ ký tự không phải chữ/số).
     */
    private const ALIASES = [
        'TOANHOC' => 'TOAN', 'MATH' => 'TOAN', 'MATHS' => 'TOAN',
        'NGUVAN' => 'VAN', 'VANHOC' => 'VAN', 'LITERATURE' => 'VAN',
        'TIENGANH' => 'ANH', 'ENG' => 'ANH', 'ENGLISH' => 'ANH',
        'VATLI' => 'LI', 'VATLY' => 'LI', 'LY' => 'LI', 'PHY' => 'LI', 'PHYSICS' => 'LI',
        'HOAHOC' => 'HOA', 'CHEM' => 'HOA', 'CHEMISTRY' => 'HOA',
        'SINHHOC' => 'SINH', 'BIO' => 'SINH', 'BIOLOGY' => 'SINH',
        'KHOAHOCTUNHIEN' => 'KHTN',
        'LICHSU' => 'SU', 'HIS' => 'SU', 'HISTORY' => 'SU',
        'DIALI' => 'DIA', 'DIALY' => 'DIA', 'GEO' => 'DIA', 'DIACHAT' => 'DIA',
        'TINHOC' => 'TIN', 'IT' => 'TIN', 'INFORMATICS' => 'TIN',
        'CONGDAN' => 'GDCD', 'GIAODUCCONGDAN' => 'GDCD',
    ];

    /** Nhãn hiển thị của 1 mã môn — mã lạ/rỗng trả về null để nơi gọi tự hiện "Chưa phân loại". */
    public static function label(?string $code): ?string
    {
        return $code !== null ? (self::SUBJECTS[$code] ?? null) : null;
    }

    /**
     * Đưa 1 chuỗi bất kỳ (mã môn trong gói ZIP, tên môn tiếng Việt có dấu, tiền tố mã câu hỏi)
     * về đúng 1 mã trong SUBJECTS — không nhận ra thì trả null (KHÔNG đoán bừa, để câu đó nằm
     * nhóm "Chưa phân loại" cho người thật gán lại còn hơn gán sai môn).
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $key = self::asciiKey($raw);
        if ($key === '') {
            return null;
        }

        if (isset(self::SUBJECTS[$key])) {
            return $key;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Tên môn viết đầy đủ có dấu ("Toán học", "Ngữ văn") -> so với chính nhãn trong SUBJECTS.
        foreach (self::SUBJECTS as $code => $label) {
            if (self::asciiKey($label) === $key) {
                return $code;
            }
        }

        return null;
    }

    /** Khối lớp hợp lệ (6-12) hoặc null. */
    public static function normalizeGrade(int|string|null $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $grade = (int) $raw;

        return in_array($grade, self::GRADES, true) ? $grade : null;
    }

    /**
     * Lấy môn/khối từ khối 'taxonomy' của gói ZIP OT360-QPACK (subject.code, subject.name,
     * grade_levels[0]) — xem Admin\ContentService::questionStoreFromZipPackage().
     *
     * @param  array  $taxonomy  $json['taxonomy'] nguyên vẹn từ question.json
     * @return array{subject: ?string, grade: ?int}
     */
    public static function fromTaxonomy(array $taxonomy): array
    {
        $subject = self::normalize($taxonomy['subject']['code'] ?? null)
            ?? self::normalize($taxonomy['subject']['name'] ?? null);

        $grades = $taxonomy['grade_levels'] ?? [];
        $grade = is_array($grades) && $grades !== [] ? self::normalizeGrade(reset($grades)) : null;

        return ['subject' => $subject, 'grade' => $grade];
    }

    /**
     * Đoán môn/khối từ TIỀN TỐ mã câu hỏi theo quy ước đặt tên đang dùng cho gói ZIP:
     * "TOAN6UOC_CHUNG_001" -> TOAN/6, "ANH7TRAC_NGHIEM_001" -> ANH/7, "NGU_VAN8DOC_HIEU_001"
     * -> VAN/8, "TIN10DP_001" -> TIN/10. Không khớp quy ước thì trả null cả 2 (xem normalize()).
     *
     * @return array{subject: ?string, grade: ?int}
     */
    public static function guessFromCode(?string $questionCode): array
    {
        if ($questionCode === null || trim($questionCode) === '') {
            return ['subject' => null, 'grade' => null];
        }

        // 1[0-2] đứng TRƯỚC [6-9] để "TIN10..." ra khối 10 chứ không phải khối 1 (không hợp lệ).
        if (! preg_match('/^([A-Za-z_\- ]{2,})(1[0-2]|[6-9])/u', trim($questionCode), $m)) {
            return ['subject' => null, 'grade' => null];
        }

        return [
            'subject' => self::normalize($m[1]),
            'grade' => self::normalizeGrade($m[2]),
        ];
    }

    /** Bỏ dấu tiếng Việt + bỏ mọi ký tự không phải chữ/số + viết hoa — khoá so khớp duy nhất. */
    private static function asciiKey(string $raw): string
    {
        $ascii = \Illuminate\Support\Str::ascii($raw);

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ascii) ?? '');
    }
}

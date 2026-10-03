<?php

namespace App\Support;

/**
 * SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha rồi ngoài trang luyện
 * tập public thêm 2 cột tỉnh thành và năm luôn nha") — DANH MỤC TỈNH THÀNH + NĂM cho Kho câu hỏi.
 *
 * Làm y đúng khuôn App\Support\SubjectCatalog (đã chạy ổn cho Môn/Khối từ 8/9):
 *   · Danh sách CỐ ĐỊNH, không phải ô gõ tự do. Ô gõ tự do là mỗi người gõ một kiểu ("Hà Nội" /
 *     "ha noi" / "TP Hà Nội") và bộ lọc theo tỉnh vỡ ngay hôm sau.
 *   · Cột questions.province lưu MÃ (khoá của 2 mảng dưới, vd "HANOI"), KHÔNG lưu nhãn tiếng
 *     Việt — sau này đổi cách viết nhãn thì không phải sửa dữ liệu.
 *
 * VÌ SAO CÓ 2 MẢNG: từ 1/7/2025 cả nước còn 34 đơn vị cấp tỉnh (6 thành phố + 28 tỉnh). Nhưng
 * kho câu hỏi này đầy đề thi HSG/chuyên của các NĂM TRƯỚC, mang tên tỉnh đã sáp nhập ("Đề HSG
 * Tin học Hải Dương 2019"). Bỏ hẳn tên cũ là không khai báo nổi nguồn đề cũ, nên giữ thành nhóm
 * riêng LEGACY_PROVINCES để form hiện ở optgroup "Tên cũ (trước sáp nhập 2025)" — vẫn chọn và
 * lọc được, mà không lẫn vào danh sách hiện hành.
 */
class ProvinceCatalog
{
    /**
     * 6 thành phố trực thuộc trung ương — khai trước để nổi lên đầu dropdown.
     *
     * @var array<string, string>
     */
    public const CITIES = [
        'HANOI' => 'Hà Nội',
        'TPHCM' => 'TP. Hồ Chí Minh',
        'HAIPHONG' => 'Hải Phòng',
        'DANANG' => 'Đà Nẵng',
        'HUE' => 'Huế',
        'CANTHO' => 'Cần Thơ',
    ];

    /**
     * 28 tỉnh hiện hành (sau sáp nhập 2025), xếp theo thứ tự chữ cái.
     *
     * @var array<string, string>
     */
    public const PROVINCES = [
        'ANGIANG' => 'An Giang',
        'BACNINH' => 'Bắc Ninh',
        'CAMAU' => 'Cà Mau',
        'CAOBANG' => 'Cao Bằng',
        'DAKLAK' => 'Đắk Lắk',
        'DIENBIEN' => 'Điện Biên',
        'DONGNAI' => 'Đồng Nai',
        'DONGTHAP' => 'Đồng Tháp',
        'GIALAI' => 'Gia Lai',
        'HATINH' => 'Hà Tĩnh',
        'HUNGYEN' => 'Hưng Yên',
        'KHANHHOA' => 'Khánh Hoà',
        'LAICHAU' => 'Lai Châu',
        'LAMDONG' => 'Lâm Đồng',
        'LANGSON' => 'Lạng Sơn',
        'LAOCAI' => 'Lào Cai',
        'NGHEAN' => 'Nghệ An',
        'NINHBINH' => 'Ninh Bình',
        'PHUTHO' => 'Phú Thọ',
        'QUANGNGAI' => 'Quảng Ngãi',
        'QUANGNINH' => 'Quảng Ninh',
        'QUANGTRI' => 'Quảng Trị',
        'SONLA' => 'Sơn La',
        'TAYNINH' => 'Tây Ninh',
        'THAINGUYEN' => 'Thái Nguyên',
        'THANHHOA' => 'Thanh Hoá',
        'TUYENQUANG' => 'Tuyên Quang',
        'VINHLONG' => 'Vĩnh Long',
    ];

    /**
     * 29 tỉnh/thành đã sáp nhập, tên không còn là đơn vị hành chính — CHỈ để khai nguồn đề thi
     * các năm trước. Hiện ở nhóm riêng trên dropdown, không trộn với danh sách hiện hành.
     *
     * @var array<string, string>
     */
    public const LEGACY_PROVINCES = [
        'BARIAVUNGTAU' => 'Bà Rịa - Vũng Tàu',
        'BACGIANG' => 'Bắc Giang',
        'BACKAN' => 'Bắc Kạn',
        'BACLIEU' => 'Bạc Liêu',
        'BENTRE' => 'Bến Tre',
        'BINHDINH' => 'Bình Định',
        'BINHDUONG' => 'Bình Dương',
        'BINHPHUOC' => 'Bình Phước',
        'BINHTHUAN' => 'Bình Thuận',
        'DAKNONG' => 'Đắk Nông',
        'HAGIANG' => 'Hà Giang',
        'HANAM' => 'Hà Nam',
        'HAUGIANG' => 'Hậu Giang',
        'HAIDUONG' => 'Hải Dương',
        'HOABINH' => 'Hoà Bình',
        'KIENGIANG' => 'Kiên Giang',
        'KONTUM' => 'Kon Tum',
        'LONGAN' => 'Long An',
        'NAMDINH' => 'Nam Định',
        'NINHTHUAN' => 'Ninh Thuận',
        'PHUYEN' => 'Phú Yên',
        'QUANGBINH' => 'Quảng Bình',
        'QUANGNAM' => 'Quảng Nam',
        'SOCTRANG' => 'Sóc Trăng',
        'THAIBINH' => 'Thái Bình',
        'TIENGIANG' => 'Tiền Giang',
        'TRAVINH' => 'Trà Vinh',
        'VINHPHUC' => 'Vĩnh Phúc',
        'YENBAI' => 'Yên Bái',
    ];

    /** Năm sớm nhất cho ô "Năm" — đủ phủ đề thi cũ mà dropdown không dài vô ích. */
    public const MIN_YEAR = 2005;

    /**
     * Cách viết khác hay gặp -> mã chuẩn. Khoá đã chuẩn hoá sẵn theo đúng cách asciiKey() làm.
     * Có cả tên cũ đã sáp nhập trỏ về mã riêng của nó (không tự quy về tỉnh mới): đề thi
     * "Hải Dương 2019" là đề của Hải Dương, đổi thành Hải Phòng là ghi sai nguồn đề.
     */
    private const ALIASES = [
        'TPHOCHIMINH' => 'TPHCM', 'HOCHIMINH' => 'TPHCM', 'SAIGON' => 'TPHCM', 'HCM' => 'TPHCM',
        'TPHANOI' => 'HANOI', 'THUDO' => 'HANOI',
        'TPHUE' => 'HUE', 'THUATHIENHUE' => 'HUE',
        'TPDANANG' => 'DANANG',
        'TPCANTHO' => 'CANTHO',
        'TPHAIPHONG' => 'HAIPHONG',
        'DACLAC' => 'DAKLAK', 'DAKLAC' => 'DAKLAK',
        'DACNONG' => 'DAKNONG',
        'VUNGTAU' => 'BARIAVUNGTAU', 'BARIA' => 'BARIAVUNGTAU',
    ];

    /**
     * Toàn bộ mã => nhãn (hiện hành + tên cũ) — dùng khi CHỈ cần tra nhãn, không cần biết nhóm.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::CITIES + self::PROVINCES + self::LEGACY_PROVINCES;
    }

    /**
     * Danh sách hiện hành (34 đơn vị) — thành phố trước, tỉnh sau.
     *
     * @return array<string, string>
     */
    public static function current(): array
    {
        return self::CITIES + self::PROVINCES;
    }

    /**
     * Dữ liệu dựng <optgroup> cho ô chọn + thanh bộ lọc: nhãn nhóm => (mã => nhãn).
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Thành phố trực thuộc trung ương' => self::CITIES,
            'Tỉnh' => self::PROVINCES,
            'Tên cũ (trước sáp nhập 2025)' => self::LEGACY_PROVINCES,
        ];
    }

    /** Nhãn của 1 mã; mã lạ/rỗng -> null để nơi gọi tự hiện "—" hoặc "Chưa gán". */
    /**
     * SỬA 3/10 (khách: "chỗ tạo câu hỏi và cập nhật trong admin và giáo viên chọn tỉnh thành thì
     * để 2 option là Toàn quốc hoặc Các tỉnh khác thôi") — PHẠM VI dùng cho CÂU HỎI.
     *
     * Chỉ áp cho ô "Tỉnh thành" ở form câu hỏi. Ô tỉnh/thành của ĐỀ THI (form đề bên admin/giáo
     * viên) GIỮ NGUYÊN danh mục 63 đơn vị — đề thi cần khai đúng nguồn ("Đề HSG Hà Tĩnh"), còn
     * câu hỏi trong kho thì chỉ cần biết nó dùng được toàn quốc hay bó hẹp theo tỉnh.
     *
     * Hai mã này nằm cùng một cột questions.province với các mã tỉnh cũ, nên label() ở dưới tra
     * CẢ hai bảng: câu hỏi cũ đã gán "HATINH" vẫn hiện đúng "Hà Tĩnh" chứ không bị đổi tên lặng
     * lẽ thành "Các tỉnh khác".
     */
    public const QUESTION_SCOPES = [
        'TOANQUOC' => 'Toàn quốc',
        'KHAC' => 'Các tỉnh khác',
    ];

    /**
     * Chuẩn hoá ô "Tỉnh thành" của FORM CÂU HỎI.
     *
     * Ưu tiên 2 phạm vi mới; không phải thì mới tra danh mục tỉnh đầy đủ — nhờ vậy sửa một câu
     * hỏi cũ đang gán "HATINH" mà không đụng ô đó thì giá trị cũ được giữ nguyên, thay vì bị
     * normalize() trả null rồi lưu thành "chưa gán" một cách lặng lẽ.
     */
    public static function normalizeForQuestion(?string $raw): ?string
    {
        return self::normalizeQuestionScope($raw) ?? self::normalize($raw);
    }

    /** Mã phạm vi hợp lệ cho câu hỏi, hoặc null (mã lạ -> null, KHÔNG đoán bừa). */
    public static function normalizeQuestionScope(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $code = strtoupper(trim($raw));

        return array_key_exists($code, self::QUESTION_SCOPES) ? $code : null;
    }

    public static function label(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return self::QUESTION_SCOPES[$code] ?? self::all()[$code] ?? null;
    }

    /**
     * Đưa 1 chuỗi bất kỳ (mã, tên có dấu, tên gõ không dấu) về đúng 1 mã — không nhận ra thì
     * trả null. CỐ Ý không đoán bừa: để câu đó "chưa gán tỉnh" cho người thật gán lại còn hơn
     * gán sai nguồn đề.
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

        $all = self::all();

        if (isset($all[$key])) {
            return $key;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // Tên viết đầy đủ có dấu ("Thanh Hoá", "Bà Rịa - Vũng Tàu") -> so với chính nhãn.
        foreach ($all as $code => $label) {
            if (self::asciiKey($label) === $key) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Các năm cho dropdown, MỚI NHẤT TRƯỚC. Lấy tới năm sau năm hiện tại vì đề thi của năm học
     * tới vẫn được soạn trước (vd tháng 10/2026 đã có đề cho kỳ thi 2027).
     *
     * @return array<int, int>
     */
    public static function years(): array
    {
        return range((int) date('Y') + 1, self::MIN_YEAR);
    }

    /** Năm hợp lệ hoặc null. Khoảng rộng hơn years() 1 chút để dữ liệu cũ không bị loại oan. */
    public static function normalizeYear(int|string|null $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $year = (int) $raw;

        return ($year >= self::MIN_YEAR && $year <= (int) date('Y') + 1) ? $year : null;
    }

    /** Bỏ dấu tiếng Việt + bỏ mọi ký tự không phải chữ/số + viết hoa — khoá so khớp duy nhất. */
    private static function asciiKey(string $raw): string
    {
        $ascii = \Illuminate\Support\Str::ascii($raw);

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ascii) ?? '');
    }
}

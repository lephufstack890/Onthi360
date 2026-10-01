<?php

namespace App\Support;

/**
 * SỬA 18/9 (khách: "chỗ giáo viên và admin thêm lọc theo độ khó nữa nha") — NƠI DUY NHẤT hiểu
 * "độ khó" của 1 câu hỏi. Trước đó luật này nằm rải rác: đoạn suy độ khó viết thẳng trong
 * Public\PracticeService::problemRows(), 4 nhãn gõ tay lặp lại ở 3 form tạo/sửa câu hỏi, còn
 * bộ lọc thì chưa có. Gom về đây để bộ lọc (SQL) và phần HIỂN THỊ (PHP) không bao giờ nói
 * khác nhau — lọc "Khó" mà ra câu ghi "Trung bình" là lỗi khó chịu nhất của loại tính năng này.
 *
 * 3 nguồn giá trị, xử lý theo đúng thứ tự ưu tiên:
 *   1. metadata.difficulty là KHOÁ người dùng chọn ở form ('easy'/'medium'/'hard'/'expert').
 *   2. metadata.difficulty là SỐ 1-5 kiểu cũ (gói ZIP đời trước khai như vậy) -> quy về khoá.
 *   3. Chưa đặt (hoặc giá trị lạ) -> SUY theo điểm câu hỏi. Vẫn là dữ liệu có thật của câu,
 *      không phải con số bịa ra.
 */
class QuestionDifficulty
{
    /**
     * Khoá => nhãn tiếng Việt. Thứ tự khai báo cũng là thứ tự hiện trên dropdown/bộ lọc.
     *
     * SỬA 30/9 (khách: "độ khó thì phân thành 1-5 sao tương ứng: Cơ bản, Dễ, Khá, Khó, Rất
     * khó") — 4 mức cũ (Dễ/Trung bình/Khó/Cực khó) thành 5 mức, mỗi mức đúng 1 sao. KHÔNG cần
     * chạy lệnh chuyển dữ liệu: normalize() ở dưới tự quy giá trị cũ về khoá mới (mức
     * "medium" cũ = 3 sao -> "fair"/Khá cũng 3 sao), và mọi chỗ lọc/hiển thị đều đi qua lớp này.
     */
    public const LEVELS = [
        'basic' => 'Cơ bản',
        'easy' => 'Dễ',
        'fair' => 'Khá',
        'hard' => 'Khó',
        'expert' => 'Rất khó',
    ];

    /**
     * Khoá CŨ => khoá mới. Dữ liệu đã lưu trong metadata.difficulty vẫn còn chữ "medium", và
     * link/bộ lọc cũ người dùng đã lưu (?difficulty=medium) vẫn phải chạy đúng.
     */
    public const LEGACY_KEYS = ['medium' => 'fair'];

    /** Giá trị lọc đặc biệt: "chưa ai đặt độ khó" (để admin/giáo viên dò ra mà gán dần). */
    public const UNSET = 'none';

    /**
     * Khoảng ĐIỂM ứng với từng mức khi câu CHƯA đặt độ khó — suy ngược từ đúng công thức
     * derive() bên dưới, dùng cho mệnh đề WHERE của bộ lọc (xem sqlConditions()).
     * Phải sửa CÙNG LÚC với derive() nếu đổi công thức, nếu không lọc sẽ lệch hiển thị.
     *
     * @var array<string, array{0:int, 1:int}>
     */
    public const POINT_RANGES = [
        'basic' => [0, 20],
        'easy' => [21, 40],
        'fair' => [41, 60],
        'hard' => [61, 80],
        'expert' => [81, PHP_INT_MAX],
    ];

    /** Số sao hiển thị (thang 5) cho từng mức — SỬA 30/9: giờ đúng 1 mức 1 sao. */
    public const STARS = ['basic' => 1, 'easy' => 2, 'fair' => 3, 'hard' => 4, 'expert' => 5];

    /**
     * SỬA 1/10 (khách: "khi chọn cơ bản là 2 điểm, Dễ là 4 điểm, khá 6 điểm, khó 8 điểm, rất khó
     * 10 điểm và khi chọn thì nó tự active vô field điểm luôn không cho nhập điểm") — ĐIỂM do
     * ĐỘ KHÓ quyết định, người soạn không gõ tay nữa.
     *
     * Đây là nơi DUY NHẤT giữ bảng quy đổi: ô "Điểm" trên form chỉ hiện lại con số (readonly),
     * còn giá trị thật LUÔN được tính lại ở server bằng pointsFor() — xem
     * Admin\ContentService::questionStore()/questionUpdate()/questionCreateNewVersion() và
     * Teacher\QuestionService::buildAttributes(). Cố ý không tin ô input: ô readonly vẫn sửa
     * được bằng DevTools, mà điểm thì ảnh hưởng trực tiếp tới kết quả chấm.
     *
     * @var array<string, int>
     */
    public const POINTS = ['basic' => 2, 'easy' => 4, 'fair' => 6, 'hard' => 8, 'expert' => 10];

    /**
     * Điểm ứng với 1 mức độ khó; null khi chưa chọn độ khó (nơi gọi giữ nguyên điểm đang có).
     * Nhận cả khoá đời cũ ("medium") qua normalize().
     */
    public static function pointsFor(mixed $key): ?int
    {
        $key = self::normalize($key);

        return $key !== null ? self::POINTS[$key] : null;
    }

    /**
     * SỬA 1/10 (khách: "chỗ add câu hỏi cho chỗ tạo đề... đừng cho nhập nhé mà tự động active
     * điểm của các câu theo độ khó của câu đó") — ĐIỂM CỦA 1 CÂU KHI GHÉP VÀO ĐỀ.
     *
     * NƠI DUY NHẤT tính con số này: màn chọn câu hỏi hiện nó ra, và service ghi
     * assessment_items.points_override cũng lấy đúng nó — hai bên lệch nhau thì admin thấy một
     * đằng mà máy chấm một nẻo.
     *
     * CỐ Ý đi qua resolve() chứ không đọc thẳng cột questions.points: câu CŨ chưa đặt độ khó còn
     * mang điểm thang cũ (mặc định 10, có câu 100). resolve() suy ra mức theo đúng cách mọi màn
     * khác đang hiển thị, rồi POINTS quy về thang mới 2/4/6/8/10 — nhờ vậy con số trên màn chọn
     * câu luôn khớp với mức độ khó ghi ngay cạnh nó.
     *
     * @param  array<string, mixed>|null  $metadata  cột questions.metadata (đã cast array).
     */
    public static function pointsForQuestion(?array $metadata, int $points): int
    {
        return self::POINTS[self::resolve($metadata, $points)];
    }

    /** Số 1-5 kiểu cũ ứng với từng mức — dùng cả khi đọc dữ liệu cũ lẫn khi lọc. */
    public const LEGACY_NUMBERS = ['basic' => [1], 'easy' => [2], 'fair' => [3], 'hard' => [4], 'expert' => [5]];

    public static function isValidKey(mixed $key): bool
    {
        return is_string($key) && array_key_exists($key, self::LEVELS);
    }

    /**
     * SỬA 30/9 — luật validate cho ô "Độ khó" ở các form câu hỏi. Sinh từ LEVELS (+ khoá đời
     * cũ) thay vì gõ tay "in:easy,medium,hard,expert" ở 4 controller — thêm/bớt 1 mức chỉ phải
     * sửa đúng 1 chỗ, không bao giờ có chuyện form cho chọn mà validate chặn.
     */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_merge(array_keys(self::LEVELS), array_keys(self::LEGACY_KEYS)));
    }

    /**
     * SỬA 30/9 — khoá lọc hợp lệ SAU KHI quy đổi khoá đời cũ: link/bookmark cũ dạng
     * ?difficulty=medium vẫn phải lọc ra đúng mức "Khá", không rơi về "không lọc gì".
     */
    public static function filterKey(mixed $key): ?string
    {
        if (! is_string($key)) {
            return null;
        }

        $key = self::LEGACY_KEYS[$key] ?? $key;

        return self::isValidKey($key) ? $key : null;
    }

    /** Nhãn tiếng Việt; khoá lạ -> "Khá" (không bao giờ trả chuỗi rỗng ra giao diện). */
    public static function label(?string $key): string
    {
        $key = self::LEGACY_KEYS[$key] ?? $key;

        return self::LEVELS[$key] ?? self::LEVELS['fair'];
    }

    public static function stars(?string $key): int
    {
        $key = self::LEGACY_KEYS[$key] ?? $key;

        return self::STARS[$key] ?? self::STARS['fair'];
    }

    /**
     * Đọc giá trị THÔ trong metadata.difficulty -> khoá chuẩn, hoặc null nếu chưa đặt/không
     * hiểu được (khi đó gọi tiếp derive() để suy theo điểm).
     */
    public static function normalize(mixed $raw): ?string
    {
        if (self::isValidKey($raw)) {
            return $raw;
        }

        // Khoá đời cũ ("medium") — quy về khoá mới cùng số sao, không để mất độ khó đã đặt.
        if (is_string($raw) && isset(self::LEGACY_KEYS[$raw])) {
            return self::LEGACY_KEYS[$raw];
        }

        // Dữ liệu cũ lưu số 1-5 — không để mất độ khó chỉ vì hệ thống đổi cách lưu.
        if (is_numeric($raw) && (int) $raw >= 1 && (int) $raw <= 5) {
            foreach (self::LEGACY_NUMBERS as $key => $numbers) {
                if (in_array((int) $raw, $numbers, true)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Suy độ khó theo ĐIỂM khi câu chưa được đặt — giữ NGUYÊN công thức đã chạy từ trước
     * (thang 5 bậc, mỗi bậc 20 điểm; câu 0 điểm coi như 10 điểm). Đổi công thức ở đây thì
     * PHẢI sửa POINT_RANGES ở trên cho khớp.
     *
     * SỬA 1/10 — ĐỪNG sửa hàm này theo thang điểm mới 2/4/6/8/10 của POINTS ở trên, dù trông
     * như là chỗ cần sửa. Hàm này CHỈ dùng cho câu CHƯA đặt độ khó, tức là câu CŨ — mà câu cũ
     * đang mang điểm theo thang cũ (mặc định 10, có câu 100). Theo thang mới thì 10 điểm = "Rất
     * khó", nên đổi công thức là MỌI câu cũ chưa gán độ khó nhảy từ "Cơ bản" sang "Rất khó" chỉ
     * sau một lần deploy. Câu MỚI thì luôn có độ khó chọn sẵn ở form nên không bao giờ đi qua
     * đây. Hai thang điểm sống song song mà không đụng nhau chính là nhờ vậy.
     */
    public static function derive(int $points): string
    {
        $guess = max(1, min(5, (int) ceil(($points ?: 10) / 20)));

        return match ($guess) {
            1 => 'basic',
            2 => 'easy',
            3 => 'fair',
            4 => 'hard',
            default => 'expert',
        };
    }

    /**
     * Độ khó CUỐI CÙNG của 1 câu hỏi: ưu tiên giá trị đã đặt, không có thì suy theo điểm.
     * Luôn trả về 1 trong 4 khoá hợp lệ.
     *
     * @param  array<string, mixed>|null  $metadata  cột questions.metadata (đã cast array).
     */
    public static function resolve(?array $metadata, int $points): string
    {
        return self::normalize($metadata['difficulty'] ?? null) ?? self::derive($points);
    }

    /** Giá trị đã đặt (nếu có) — dùng cho ô "Độ khó" ở form Sửa, KHÔNG suy theo điểm. */
    public static function stored(?array $metadata): ?string
    {
        return self::normalize($metadata['difficulty'] ?? null);
    }

    /**
     * MỌI giá trị được coi là "đã đặt" (khoá mới + số cũ, cả dạng chuỗi lẫn số) — bộ lọc dùng
     * để phân biệt câu ĐÃ đặt với câu phải suy theo điểm. Ghép chuỗi lẫn số vì MySQL đọc
     * metadata.difficulty ra chuỗi, còn Eloquent bind theo đúng kiểu PHP truyền vào.
     *
     * @return array<int, int|string>
     */
    public static function allStoredValues(): array
    {
        $values = array_keys(self::LEVELS);

        // Cả khoá đời cũ còn nằm trong DB — thiếu nó thì câu lưu "medium" bị coi là "chưa đặt".
        foreach (self::LEGACY_KEYS as $oldKey => $newKey) {
            $values[] = $oldKey;
        }

        foreach (self::LEGACY_NUMBERS as $numbers) {
            foreach ($numbers as $n) {
                $values[] = $n;
                $values[] = (string) $n;
            }
        }

        return $values;
    }

    /**
     * Giá trị "đã đặt" ứng với ĐÚNG 1 mức — vế thứ nhất của bộ lọc.
     *
     * @return array<int, int|string>
     */
    public static function storedValuesFor(string $key): array
    {
        $values = [$key];

        foreach (self::LEGACY_KEYS as $oldKey => $newKey) {
            if ($newKey === $key) {
                $values[] = $oldKey;
            }
        }

        foreach (self::LEGACY_NUMBERS[$key] ?? [] as $n) {
            $values[] = $n;
            $values[] = (string) $n;
        }

        return $values;
    }
}

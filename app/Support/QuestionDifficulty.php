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

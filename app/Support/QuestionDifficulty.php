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
    /** Khoá => nhãn tiếng Việt. Thứ tự khai báo cũng là thứ tự hiện trên dropdown/bộ lọc. */
    public const LEVELS = [
        'easy' => 'Dễ',
        'medium' => 'Trung bình',
        'hard' => 'Khó',
        'expert' => 'Cực khó',
    ];

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
        'easy' => [0, 40],
        'medium' => [41, 60],
        'hard' => [61, 80],
        'expert' => [81, PHP_INT_MAX],
    ];

    /** Số sao hiển thị (thang 5) cho từng mức. */
    public const STARS = ['easy' => 2, 'medium' => 3, 'hard' => 4, 'expert' => 5];

    /** Số 1-5 kiểu cũ ứng với từng mức — dùng cả khi đọc dữ liệu cũ lẫn khi lọc. */
    public const LEGACY_NUMBERS = ['easy' => [1, 2], 'medium' => [3], 'hard' => [4], 'expert' => [5]];

    public static function isValidKey(mixed $key): bool
    {
        return is_string($key) && array_key_exists($key, self::LEVELS);
    }

    /** Nhãn tiếng Việt; khoá lạ -> "Trung bình" (không bao giờ trả chuỗi rỗng ra giao diện). */
    public static function label(?string $key): string
    {
        return self::LEVELS[$key] ?? self::LEVELS['medium'];
    }

    public static function stars(?string $key): int
    {
        return self::STARS[$key] ?? self::STARS['medium'];
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
            1, 2 => 'easy',
            3 => 'medium',
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

        foreach (self::LEGACY_NUMBERS[$key] ?? [] as $n) {
            $values[] = $n;
            $values[] = (string) $n;
        }

        return $values;
    }
}

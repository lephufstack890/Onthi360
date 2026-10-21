<?php

namespace App\Support;

/**
 * SỬA 21/10 (khách: "thêm checker cho câu lập trình" — ĐỢT 1) — CÁCH SO kết quả in ra với đáp án của 1 câu lập trình.
 * NƠI DUY NHẤT chứa luật so khớp: CodeJudgingService (chấm gộp + chấm từng test) đều gọi matches() ở đây.
 *
 * Lưu trong questions.grading_config['checker'] = ['mode' => ..., 'epsilon' => ...]. Câu KHÔNG có khoá này = 'default'
 * (y hệt cách so trước đây) nên câu cũ không đổi gì.
 *
 *   default     : so từng dòng, bỏ khoảng trắng cuối dòng + dòng trống ở cuối (cách cũ).
 *   tokens      : so theo từ — bỏ qua mọi khoảng trắng / xuống dòng, chỉ so dãy từ / số.
 *   float       : như tokens, nhưng 2 SỐ lệch nhau không quá ε (tuyệt đối, hoặc tương đối với số lớn) là đúng.
 *   ignore_case : như default nhưng không phân biệt hoa thường (YES = yes).
 *   unordered   : như default nhưng không xét thứ tự các dòng.
 *
 * Nhập: form tạo / sửa câu (ô "Cách chấm" — partials/coding-checker-fields) qua fromInput(); gói ZIP qua normalize()
 * (question.json: "grading": { "checker": { "type": "float", "epsilon": 0.000001 } } hoặc "checker": "tokens").
 * ĐỢT 2 (checker testlib tự viết) sẽ thêm mode 'custom' ở đây.
 */
final class OutputChecker
{
    public const DEFAULT = 'default';

    public const MODES = [
        'default' => 'Mặc định — so từng dòng',
        'tokens' => 'So theo từ — bỏ qua khoảng trắng / xuống dòng',
        'float' => 'Số thực — cho phép sai số ε',
        'ignore_case' => 'Không phân biệt hoa thường',
        'unordered' => 'Không xét thứ tự dòng',
    ];

    public const HINTS = [
        'default' => 'Giống từng dòng với đáp án; bỏ qua khoảng trắng cuối dòng và dòng trống ở cuối.',
        'tokens' => 'Chỉ so dãy từ / số theo thứ tự — in cách 1 hay nhiều dấu cách, xuống dòng chỗ nào cũng được.',
        'float' => 'Như "So theo từ", nhưng 2 số lệch nhau không quá ε là đúng (vd 3.3333333 và 3.333333 với ε = 0.000001).',
        'ignore_case' => 'Như Mặc định nhưng YES / yes / Yes coi là giống nhau.',
        'unordered' => 'Đúng đủ các dòng của đáp án là được, thứ tự nào cũng chấp nhận.',
    ];

    public const DEFAULT_EPSILON = 0.000001;

    /**
     * Chuẩn hoá cấu hình (từ grading_config đã lưu hoặc từ gói ZIP). Trả null = Mặc định (không cần lưu).
     *
     * @return array{mode: string, epsilon?: float}|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $raw = ['mode' => $raw];
        }
        if (! is_array($raw)) {
            return null;
        }

        $mode = strtolower(trim((string) ($raw['mode'] ?? $raw['type'] ?? self::DEFAULT)));
        $mode = match ($mode) {
            'token', 'words', 'word' => 'tokens',
            'real', 'double', 'number' => 'float',
            'case_insensitive', 'nocase', 'ignorecase' => 'ignore_case',
            'unordered_lines', 'any_order', 'sorted' => 'unordered',
            default => $mode,
        };

        if (! array_key_exists($mode, self::MODES) || $mode === self::DEFAULT) {
            return null;
        }

        if ($mode !== 'float') {
            return ['mode' => $mode];
        }

        $eps = $raw['epsilon'] ?? $raw['eps'] ?? null;
        $eps = is_numeric($eps) ? (float) $eps : self::DEFAULT_EPSILON;
        if (! ($eps > 0) || $eps > 1) {
            $eps = self::DEFAULT_EPSILON;
        }

        return ['mode' => 'float', 'epsilon' => $eps];
    }

    /**
     * Từ form tạo / sửa câu: ô checker_mode + checker_epsilon. Form KHÔNG có ô này (form khác chưa thêm) thì giữ nguyên
     * cấu hình đang có ($data['_existing_checker']) — không lỡ tay xoá cách chấm đã chọn.
     *
     * @return array{mode: string, epsilon?: float}|null
     */
    public static function fromInput(array $data): ?array
    {
        if (! array_key_exists('checker_mode', $data)) {
            return self::normalize($data['_existing_checker'] ?? null);
        }

        return self::normalize(['mode' => $data['checker_mode'], 'epsilon' => $data['checker_epsilon'] ?? null]);
    }

    public static function mode(?array $checker): string
    {
        return self::normalize($checker)['mode'] ?? self::DEFAULT;
    }

    public static function isDefault(?array $checker): bool
    {
        return self::mode($checker) === self::DEFAULT;
    }

    /** Nhãn ngắn để hiện ở trang chi tiết câu: "Số thực — cho phép sai số ε (ε = 1.0E-6)". */
    public static function label(?array $checker): string
    {
        $c = self::normalize($checker);
        $mode = $c['mode'] ?? self::DEFAULT;

        return self::MODES[$mode].($mode === 'float' ? ' (ε = '.self::formatEpsilon($c['epsilon']).')' : '');
    }

    public static function formatEpsilon(float $eps): string
    {
        return rtrim(rtrim(number_format($eps, 12, '.', ''), '0'), '.');
    }

    /** Kết quả in ra có được tính là ĐÚNG với đáp án không, theo cách chấm của câu. */
    public static function matches(string $actual, string $expected, ?array $checker = null): bool
    {
        $c = self::normalize($checker);

        return match ($c['mode'] ?? self::DEFAULT) {
            'tokens' => self::tokens($actual) === self::tokens($expected),
            'float' => self::floatTokensMatch(self::tokens($actual), self::tokens($expected), (float) $c['epsilon']),
            'ignore_case' => mb_strtolower(implode("\n", self::lines($actual))) === mb_strtolower(implode("\n", self::lines($expected))),
            'unordered' => self::sorted(self::lines($actual)) === self::sorted(self::lines($expected)),
            default => self::lines($actual) === self::lines($expected),
        };
    }

    /** Các dòng sau khi bỏ \r, khoảng trắng / tab cuối dòng và dòng trống ở cuối (luật Mặc định cũ). */
    private static function lines(string $text): array
    {
        $rows = array_map(
            static fn ($l) => rtrim($l, " \t"),
            explode("\n", str_replace(["\r\n", "\r"], "\n", $text))
        );
        while ($rows !== [] && end($rows) === '') {
            array_pop($rows);
        }

        return $rows;
    }

    private static function sorted(array $rows): array
    {
        sort($rows, SORT_STRING);

        return $rows;
    }

    private static function tokens(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function floatTokensMatch(array $a, array $e, float $eps): bool
    {
        if (count($a) !== count($e)) {
            return false;
        }

        foreach ($e as $i => $want) {
            $got = $a[$i];
            if ($got === $want) {
                continue;
            }
            if (! is_numeric($got) || ! is_numeric($want)) {
                return false;
            }
            $x = (float) $got;
            $y = (float) $want;
            // Lệch tuyệt đối ≤ ε, hoặc lệch tương đối ≤ ε khi đáp án lớn (giống checker số thực của testlib).
            if (abs($x - $y) > $eps * max(1.0, abs($y))) {
                return false;
            }
        }

        return true;
    }
}

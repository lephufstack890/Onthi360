<?php

namespace App\Support;

use App\Enums\AnswerSheetQuestionType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 9/9 (khách: "thêm nút tải file Excel mẫu về để điền" + "nút upload file Excel đáp án",
 * sau đó: "đủ các loại như file mẫu này nè, thiếu thì thêm vô nhé") — sinh FILE MẪU và ĐỌC file
 * đáp án cho màn "Quản lý đề PDF", hiểu ĐỦ các dạng trong file mẫu khách gửi.
 *
 * Ánh xạ với file mẫu của khách:
 *   "Chọn đáp án đúng" -> Trắc nghiệm A/B/C/D          (single_choice)
 *   "Đúng sai"         -> Đúng/Sai                      (true_false — 1 giá trị Đ/S)
 *                         hoặc Đúng/Sai 4 ý             (true_false_group — ghi 4 giá trị "Đ S Đ S")
 *                         => TỰ NHẬN BIẾT theo số giá trị trong ô đáp án
 *   "Điền đáp án"      -> Trả lời ngắn                  (short_answer)
 *   "Câu nhiều ý"      -> Câu nhiều ý                   (multi_part — "a:A-b:T-c:123")
 *   "Lập trình"        -> KHÔNG dùng cho phiếu đáp án (xem TYPE_NOT_SUPPORTED bên dưới)
 *
 * Cố ý DỄ TÍNH với cách gõ (nhiều bí danh cho tên dạng, nhiều kiểu ghi Đúng/Sai, dấu phẩy thập
 * phân kiểu Việt) nhưng NGHIÊM với dữ liệu sai: mọi dòng hỏng đều gom lại báo rõ số dòng, không
 * bao giờ đoán bừa đáp án — đoán sai ở đây là chấm sai cho cả lớp.
 */
class AnswerKeySheet
{
    /** Số dòng dựng sẵn trong file mẫu để giáo viên điền thẳng. */
    private const TEMPLATE_ROWS = 40;

    public const HEADERS = ['Câu', 'Dạng', 'Đáp án', 'Điểm'];

    /** Nhãn dạng câu ghi ra file mẫu. */
    private const TYPE_LABELS = [
        'single_choice' => 'Trắc nghiệm',
        'true_false' => 'Đúng/Sai',
        'true_false_group' => 'Đúng/Sai 4 ý',
        'short_answer' => 'Trả lời ngắn',
        'multi_part' => 'Câu nhiều ý',
    ];

    /**
     * Cách gõ được chấp nhận ở cột "Dạng" (đã bỏ dấu, bỏ ký tự không phải chữ số, viết thường).
     * Gồm cả nhãn trong file mẫu khách gửi để file cũ vẫn nhập được.
     *
     * 'dungsai' cố ý để null: chưa quyết được là 1 ý hay 4 ý, phải nhìn ô đáp án mới biết
     * (xem resolveTrueFalseType()).
     */
    private const TYPE_ALIASES = [
        'tracnghiem' => 'single_choice',
        'chondapandung' => 'single_choice',
        'chondapan' => 'single_choice',
        'tn' => 'single_choice',
        'abcd' => 'single_choice',
        'singlechoice' => 'single_choice',

        'dungsai' => null,
        'ds' => null,

        'dungsai1y' => 'true_false',
        'dungsaicacau' => 'true_false',
        'truefalse' => 'true_false',

        'dungsai4y' => 'true_false_group',
        'dungsaitungy' => 'true_false_group',
        'truefalsegroup' => 'true_false_group',

        'traloingan' => 'short_answer',
        'diendapan' => 'short_answer',
        'dienkhuyet' => 'short_answer',
        'shortanswer' => 'short_answer',

        'caunhieuy' => 'multi_part',
        'nhieuy' => 'multi_part',
        'cauhoinhieuy' => 'multi_part',
        'multipart' => 'multi_part',
    ];

    /**
     * Dạng có trong file mẫu nhưng KHÔNG đặt được vào phiếu đáp án: câu lập trình chấm bằng cách
     * chạy code với bộ test case (App\Models\AssessmentCodingItem), không thể gói vào 1 ô đáp án.
     */
    private const TYPE_NOT_SUPPORTED = [
        'laptrinh' => 'Lập trình',
        'coding' => 'Lập trình',
        'programming' => 'Lập trình',
    ];

    /** Nội dung nhị phân của tệp .xlsx mẫu. */
    public static function templateXlsx(): string
    {
        $rows = [self::HEADERS];

        // 5 dòng đầu: mỗi dạng 1 ví dụ, nhìn là biết cách gõ.
        $rows[] = ['1', self::TYPE_LABELS['single_choice'], 'A', '1'];
        $rows[] = ['2', self::TYPE_LABELS['true_false'], 'Đ', '1'];
        $rows[] = ['3', self::TYPE_LABELS['true_false_group'], 'Đ S Đ S', '1'];
        $rows[] = ['4', self::TYPE_LABELS['short_answer'], '12.5', '1'];
        $rows[] = ['5', self::TYPE_LABELS['multi_part'], 'a:A-b:Đ-c:123', '1'];

        // Các dòng còn lại: sẵn số câu + dạng mặc định, giáo viên chỉ gõ cột "Đáp án".
        for ($no = 6; $no <= self::TEMPLATE_ROWS; $no++) {
            $rows[] = [(string) $no, self::TYPE_LABELS['single_choice'], '', '1'];
        }

        return SimpleXlsx::write($rows, 'Đáp án', [8, 18, 30, 8]);
    }

    /** Tên tệp mẫu khi tải về. */
    public static function templateFilename(?string $examCode = null): string
    {
        $suffix = filled($examCode) ? '-'.Str::slug($examCode) : '';

        return 'mau-dap-an'.$suffix.'.xlsx';
    }

    /**
     * Đọc các hàng lấy từ SimpleXlsx::readRows() thành danh sách dòng đáp án dùng được cho
     * PdfAssessmentEditingService::update().
     *
     * @param  array<int, array<int, string>>  $sheetRows
     * @return array<int, array{question_no:int, question_type:string, correct_answer:mixed, points:int}>
     *
     * @throws ValidationException nếu không có dòng hợp lệ nào, hoặc có dòng sai dữ liệu.
     */
    public static function parse(array $sheetRows): array
    {
        [$headerIndex, $columns] = self::locateHeader($sheetRows);

        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($sheetRows as $i => $cells) {
            if ($i <= $headerIndex) {
                continue;
            }

            $excelRowNo = $i + 1; // đúng số dòng người dùng thấy trong Excel
            $get = fn (string $key) => trim((string) ($cells[$columns[$key]] ?? ''));

            $noRaw = $get('question_no');
            $typeRaw = $get('question_type');
            $answerRaw = $get('correct_answer');
            $pointsRaw = $get('points');

            // Dòng trống hoàn toàn: bỏ qua (file mẫu có sẵn nhiều dòng chưa điền).
            if ($noRaw === '' && $typeRaw === '' && $answerRaw === '') {
                continue;
            }

            $no = filter_var($noRaw, FILTER_VALIDATE_INT);
            if ($no === false || $no < 1) {
                $errors[] = "Dòng {$excelRowNo}: cột \"Câu\" phải là số thứ tự câu (1, 2, 3…), đang là \"{$noRaw}\".";

                continue;
            }

            if (isset($seen[$no])) {
                $errors[] = "Dòng {$excelRowNo}: câu {$no} bị khai báo trùng (đã có ở dòng {$seen[$no]}).";

                continue;
            }

            $typeKey = self::key($typeRaw);

            if (isset(self::TYPE_NOT_SUPPORTED[$typeKey])) {
                $errors[] = "Dòng {$excelRowNo}: dạng \"".self::TYPE_NOT_SUPPORTED[$typeKey]."\" không đặt được vào phiếu đáp án — câu lập trình chấm bằng bộ test case ở mục \"Bài lập trình\", không phải bằng một ô đáp án.";

                continue;
            }

            if (! array_key_exists($typeKey, self::TYPE_ALIASES)) {
                $errors[] = "Dòng {$excelRowNo}: cột \"Dạng\" chưa hiểu được (\"{$typeRaw}\") — ghi ".implode(', ', array_values(self::TYPE_LABELS)).'.';

                continue;
            }

            // Câu chưa điền đáp án: bỏ qua để giáo viên nhập dần, không bắt điền hết một lần.
            if ($answerRaw === '') {
                continue;
            }

            $type = self::TYPE_ALIASES[$typeKey] ?? self::resolveTrueFalseType($answerRaw);

            $answer = self::parseAnswer($type, $answerRaw, $excelRowNo, $errors);
            if ($answer === null) {
                continue;
            }

            $points = $pointsRaw === '' ? 1 : filter_var($pointsRaw, FILTER_VALIDATE_INT);
            if ($points === false || $points < 0) {
                $errors[] = "Dòng {$excelRowNo}: cột \"Điểm\" phải là số nguyên từ 0 trở lên, đang là \"{$pointsRaw}\".";

                continue;
            }

            $seen[$no] = $excelRowNo;
            $rows[] = [
                'question_no' => $no,
                'question_type' => $type,
                'correct_answer' => $answer,
                'points' => $points,
            ];
        }

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 10);
            if (count($errors) > 10) {
                $shown[] = '… và '.(count($errors) - 10).' dòng lỗi khác.';
            }

            throw ValidationException::withMessages(['answer_sheet' => $shown]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'answer_sheet' => 'Không đọc được câu nào có đáp án trong tệp — kiểm tra lại cột "Câu", "Dạng", "Đáp án".',
            ]);
        }

        usort($rows, fn ($a, $b) => $a['question_no'] <=> $b['question_no']);

        return $rows;
    }

    /**
     * Ghi "Đúng sai" chung chung thì nhìn Ô ĐÁP ÁN để biết là 1 ý hay 4 ý: 1 giá trị -> cả câu
     * Đúng/Sai; từ 2 giá trị trở lên -> Đúng/Sai từng ý (a,b,c,d).
     */
    private static function resolveTrueFalseType(string $answerRaw): string
    {
        return count(self::trueFalseTokens($answerRaw)) > 1
            ? AnswerSheetQuestionType::TrueFalseGroup->value
            : AnswerSheetQuestionType::TrueFalse->value;
    }

    /**
     * Đọc ô đáp án của dạng "Câu nhiều ý" — cú pháp đúng như file mẫu khách gửi:
     * "a:A-b:T-c:123" (ngăn cách bằng "-", ";", "|" hoặc xuống dòng; KHÔNG dùng dấu phẩy vì
     * dấu phẩy còn là dấu thập phân kiểu Việt: "b:12,5").
     *
     * KIỂU của từng ý suy ra từ chính giá trị:
     *   Đ/S/T/F/đúng/sai/true/false -> Đúng/Sai · số ("123", "12,5") -> Trả lời ngắn ·
     *   một chữ cái khác (A, B, C, D…) -> Trắc nghiệm.
     * Ở dạng này CÓ phân biệt dấu: "Đ" = Đúng, còn "D" (không dấu) = phương án D của trắc nghiệm.
     *
     * @return array<string, array{type:string, value:mixed}>|null null = ô sai (đã ghi lỗi)
     */
    public static function parseMultiPartAnswer(string $raw, string $where, array &$errors): ?array
    {
        $chunks = preg_split('/[;|\n\r]+|-(?=\s*[A-Za-z]\s*:)/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (! preg_match('/^([A-Za-z])\s*:\s*(.+)$/u', $chunk, $m)) {
                $errors[] = "{$where}: ý \"{$chunk}\" chưa đúng cú pháp — mỗi ý ghi theo dạng \"a:A\", các ý cách nhau bằng dấu \"-\" (ví dụ a:A-b:Đ-c:123).";

                return null;
            }

            $part = strtolower($m[1]);
            $value = trim($m[2]);

            if (isset($out[$part])) {
                $errors[] = "{$where}: ý \"{$part}\" bị ghi trùng.";

                return null;
            }

            // Ở ĐÂY dùng bản NGHIÊM (không bỏ dấu): trong câu nhiều ý, "Đ" là Đúng còn "D" là
            // phương án D của trắc nghiệm — bỏ dấu thì hai thứ này trùng nhau, đọc sai đáp án.
            $bool = self::trueFalseValueStrict($value);
            if ($bool !== null) {
                $out[$part] = ['type' => AnswerSheetQuestionType::TrueFalse->value, 'value' => $bool];

                continue;
            }

            $numeric = str_replace([' ', ','], ['', '.'], $value);
            if (is_numeric($numeric)) {
                $out[$part] = ['type' => AnswerSheetQuestionType::ShortAnswer->value, 'value' => $numeric];

                continue;
            }

            if (preg_match('/^[A-Za-z]$/', $value)) {
                $out[$part] = ['type' => AnswerSheetQuestionType::SingleChoice->value, 'value' => strtoupper($value)];

                continue;
            }

            $errors[] = "{$where}: ý \"{$part}\" có đáp án \"{$value}\" chưa hiểu được — dùng 1 chữ cái (A/B/C/D), Đ/S, hoặc một số.";

            return null;
        }

        if ($out === []) {
            $errors[] = "{$where}: câu nhiều ý chưa có ý nào — ví dụ a:A-b:Đ-c:123.";

            return null;
        }

        ksort($out);

        return $out;
    }

    /**
     * SỬA 9/9 — chuẩn hoá 1 ô đáp án gửi lên TỪ FORM nhập tay (khác parse() ở trên: đó là đọc từ
     * tệp Excel). Gom về đây vì trước kia Admin\ContentController và Teacher\AssessmentController
     * mỗi bên giữ 1 bản normalizeAnswerSheetValue() giống hệt nhau — thêm dạng câu mới mà quên
     * sửa 1 bên là 2 màn chấm khác nhau.
     *
     * Riêng "Câu nhiều ý": form gửi lên 1 CHUỖI đúng cú pháp Excel ("a:A-b:Đ-c:123") thay vì bắt
     * admin/giáo viên bấm dựng từng ý — dùng chung đúng bộ đọc với tệp Excel nên 2 đường nhập
     * không bao giờ hiểu khác nhau.
     *
     * @throws ValidationException nếu ô "Câu nhiều ý" sai cú pháp.
     */
    public static function normalizeFormAnswer(string $questionType, mixed $raw): mixed
    {
        if ($questionType === AnswerSheetQuestionType::SingleChoice->value) {
            return strtoupper(trim((string) $raw));
        }

        if ($questionType === AnswerSheetQuestionType::ShortAnswer->value) {
            return trim((string) $raw);
        }

        if ($questionType === AnswerSheetQuestionType::TrueFalse->value) {
            return (bool) ((int) $raw);
        }

        if ($questionType === AnswerSheetQuestionType::TrueFalseGroup->value) {
            return collect((array) $raw)->mapWithKeys(fn ($v, $k) => [$k => (bool) ((int) $v)])->all();
        }

        if ($questionType === AnswerSheetQuestionType::MultiPart->value) {
            // Đã là cấu trúc sẵn (dữ liệu nhập từ Excel đổ ngược ra form rồi gửi lại) -> giữ nguyên.
            if (is_array($raw)) {
                return $raw;
            }

            $errors = [];
            $parsed = self::parseMultiPartAnswer((string) $raw, 'Câu nhiều ý', $errors);

            if ($parsed === null) {
                throw ValidationException::withMessages(['answer_keys' => $errors]);
            }

            return $parsed;
        }

        return $raw;
    }

    /**
     * Ô "Câu nhiều ý" hiển thị ngược ra form dưới dạng chuỗi "a:A-b:Đ-c:123" để sửa tay được.
     *
     * @param  array<string, mixed>  $answer
     */
    public static function multiPartToText(array $answer): string
    {
        $chunks = [];

        foreach ($answer as $part => $spec) {
            $type = is_array($spec) ? ($spec['type'] ?? null) : null;
            $value = is_array($spec) ? ($spec['value'] ?? null) : $spec;

            $text = match ($type) {
                AnswerSheetQuestionType::TrueFalse->value => $value ? 'Đ' : 'S',
                default => (string) $value,
            };

            $chunks[] = $part.':'.$text;
        }

        return implode('-', $chunks);
    }

    /**
     * Tìm dòng tiêu đề và vị trí 4 cột. Cho phép đổi thứ tự cột hoặc chèn dòng ghi chú phía trên
     * bảng; không thấy tiêu đề thì coi dòng đầu là tiêu đề và dùng thứ tự cột mặc định.
     *
     * @param  array<int, array<int, string>>  $sheetRows
     * @return array{0:int, 1:array<string,int>}
     */
    private static function locateHeader(array $sheetRows): array
    {
        $default = ['question_no' => 0, 'question_type' => 1, 'correct_answer' => 2, 'points' => 3];

        foreach ($sheetRows as $i => $cells) {
            $keys = array_map(fn ($c) => self::key((string) $c), $cells);

            if (in_array('cau', $keys, true) && (in_array('dang', $keys, true) || in_array('dapan', $keys, true))) {
                $columns = $default;
                foreach ($keys as $col => $key) {
                    $field = match ($key) {
                        'cau', 'socau', 'stt' => 'question_no',
                        'dang', 'dangcau', 'loai', 'loaicau' => 'question_type',
                        'dapan', 'dapandung' => 'correct_answer',
                        'diem' => 'points',
                        default => null,
                    };
                    if ($field !== null) {
                        $columns[$field] = $col;
                    }
                }

                return [$i, $columns];
            }
        }

        return [0, $default];
    }

    /**
     * @param  array<int, string>  $errors  (tham chiếu — thêm mô tả lỗi nếu ô đáp án sai)
     * @return string|bool|array<string, mixed>|null  null = ô sai, đã ghi lỗi
     */
    private static function parseAnswer(string $type, string $raw, int $excelRowNo, array &$errors): string|bool|array|null
    {
        $where = "Dòng {$excelRowNo}";

        if ($type === AnswerSheetQuestionType::SingleChoice->value) {
            $letter = strtoupper(trim($raw));
            if (! preg_match('/^[A-Z]$/', $letter)) {
                $errors[] = "{$where}: đáp án trắc nghiệm phải là 1 chữ cái (A, B, C, D…), đang là \"{$raw}\".";

                return null;
            }

            return $letter;
        }

        if ($type === AnswerSheetQuestionType::ShortAnswer->value) {
            // Chấp nhận dấu phẩy thập phân kiểu Việt ("12,5") — chấm điểm so bằng giá trị số.
            $value = str_replace([' ', ','], ['', '.'], trim($raw));
            if (! is_numeric($value)) {
                $errors[] = "{$where}: đáp án trả lời ngắn phải là một số, đang là \"{$raw}\".";

                return null;
            }

            return $value;
        }

        if ($type === AnswerSheetQuestionType::MultiPart->value) {
            return self::parseMultiPartAnswer($raw, $where, $errors);
        }

        if ($type === AnswerSheetQuestionType::TrueFalse->value) {
            $value = self::trueFalseValue(trim($raw));
            if ($value === null) {
                $errors[] = "{$where}: đáp án Đúng/Sai không hiểu được (\"{$raw}\") — dùng Đ/S, T/F hoặc 1/0.";

                return null;
            }

            return $value;
        }

        // true_false_group: cần đúng 4 giá trị cho 4 ý a, b, c, d.
        $tokens = self::trueFalseTokens($raw);
        if (count($tokens) !== 4) {
            $errors[] = "{$where}: câu Đúng/Sai 4 ý cần đúng 4 giá trị — ví dụ \"Đ S Đ S\", đang là \"{$raw}\".";

            return null;
        }

        $out = [];
        foreach (['a', 'b', 'c', 'd'] as $i => $part) {
            $value = self::trueFalseValue($tokens[$i]);
            if ($value === null) {
                $errors[] = "{$where}: ý {$part} không hiểu được (\"{$tokens[$i]}\") — dùng Đ/S, T/F hoặc 1/0.";

                return null;
            }
            $out[$part] = $value;
        }

        return $out;
    }

    /**
     * Tách ô đáp án Đúng/Sai thành các phần, chấp nhận nhiều cách gõ:
     * "Đ" · "ĐSĐS" · "Đ S Đ S" · "Đ,S,Đ,S" · "Đ-S-Đ-S" · "1010" · "a:Đ-b:S-c:Đ-d:S".
     *
     * @return array<int, string>
     */
    private static function trueFalseTokens(string $raw): array
    {
        $value = trim($raw);

        if ($value === '') {
            return [];
        }

        // Dạng có nhãn ý ("a:Đ-b:S…") — lấy phần sau dấu hai chấm, theo đúng thứ tự a,b,c,d.
        if (str_contains($value, ':')) {
            $found = [];
            if (preg_match_all('/([a-dA-D])\s*:\s*([^\s,;\-\/|]+)/u', $value, $m, PREG_SET_ORDER)) {
                foreach ($m as $one) {
                    $found[strtolower($one[1])] = $one[2];
                }
            }

            $ordered = [];
            foreach (['a', 'b', 'c', 'd'] as $part) {
                if (isset($found[$part])) {
                    $ordered[] = $found[$part];
                }
            }

            return $ordered;
        }

        // Có dấu ngăn cách rõ ràng
        $parts = preg_split('/[\s,;|\/\-]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) > 1) {
            return $parts;
        }

        // Viết dính: "ĐSĐS" / "TFTF" / "1010" — 1 ký tự thì là dạng Đúng/Sai cả câu.
        return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Bản NGHIÊM của trueFalseValue(): giữ nguyên dấu tiếng Việt nên phân biệt được "Đ" (Đúng)
     * với "D" (phương án D). Chỉ dùng cho câu nhiều ý — nơi 1 ý có thể là trắc nghiệm A/B/C/D
     * hoặc Đúng/Sai, phải nhìn chính ký tự để biết là ý kiểu gì.
     */
    public static function trueFalseValueStrict(string $token): ?bool
    {
        $value = mb_strtolower(trim($token));

        return match ($value) {
            'đ', 'đúng', 'dung', 't', 'true', '1', 'x', 'y', 'yes' => true,
            's', 'sai', 'f', 'false', '0', 'n', 'no' => false,
            default => null,
        };
    }

    /** Đọc 1 giá trị Đúng/Sai; không nhận ra thì trả null để nơi gọi báo lỗi. */
    public static function trueFalseValue(string $token): ?bool
    {
        return match (self::key($token)) {
            'd', 'dung', 't', 'true', '1', 'x', 'y', 'yes' => true,
            's', 'sai', 'f', 'false', '0', 'n', 'no' => false,
            default => null,
        };
    }

    /** Bỏ dấu tiếng Việt + bỏ ký tự không phải chữ/số + viết thường — khoá so khớp duy nhất. */
    private static function key(string $raw): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii(trim($raw))) ?? '');
    }
}

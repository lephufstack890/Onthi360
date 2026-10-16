<?php

namespace App\Support;

use App\Enums\AnswerSheetQuestionType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 16/10 (khách: "thêm phần nhập file ZIP chỗ tạo đề / bộ bài trong Kho bài tập / câu hỏi và đề, admin và giáo viên") —
 * đọc gói đề OT360-EPACK 2.0 (mẫu: public/templates/mau-de-thi.zip).
 *
 *   exam.json      thông tin đề + "questions": danh sách câu, MỖI CÂU TỰ KHAI "type" -> hệ thống tự phân biệt dạng
 *                  (cùng bộ giá trị với gói câu hỏi OT360-QPACK / MAU-CAU-HOI.zip):
 *                    single_choice  -> phiếu đáp án "Trắc nghiệm"      grading.choices + correct_answer "B"
 *                    true_false     -> "Đúng/Sai" (1 ý)                 correct_answer true|false
 *                                      (correct_answer là MẢNG [true,false,…] -> "Đúng/Sai 4 ý")
 *                    short_answer   -> "Trả lời ngắn"                   accepted_answers ["8","8.0"] (chữ hay số đều được)
 *                    composite      -> câu nhiều ý: toàn ý đúng/sai -> "Đúng/Sai 4 ý", trộn dạng -> "Câu nhiều ý"
 *                    programming    -> BÀI LẬP TRÌNH của đề, đọc từ thư mục "folder" đúng khuôn QPACK (question.json + tests/)
 *                  Câu nào có "folder" thì dạng/đáp án lấy theo question.json trong thư mục đó (nếu exam.json không ghi).
 *   de.pdf         đề (bắt buộc) · loi-giai.pdf · xem-truoc.pdf · bia.jpg · huong-dan.pdf · bai-mau.pdf (tuỳ chọn, tên đổi
 *                  được ở mục "files").
 *
 * Chỉ ĐỌC + KIỂM TRA, không ghi gì vào CSDL — việc tạo đề do App\Services\ExamZipImportService làm. Tệp PDF/ảnh được giải
 * nén ra thư mục tạm và bọc thành UploadedFile để đi lại ĐÚNG các hàm lưu tệp sẵn có (cùng thư mục, cùng disk); gọi
 * cleanup() khi xong. Lỗi dữ liệu gom đủ rồi báo một lần, mỗi dòng chỉ rõ câu nào sai gì (khoá lỗi 'exam_zip').
 */
class ExamZipPackage
{
    public const SCHEMA_PREFIX = 'OT360-EPACK';

    private const DEFAULT_FILES = [
        'pdf' => 'de.pdf', 'solution' => 'loi-giai.pdf', 'preview' => 'xem-truoc.pdf',
        'cover' => 'bia.jpg', 'guide' => 'huong-dan.pdf', 'sample' => 'bai-mau.pdf',
    ];

    public const TYPE_LABELS = [
        'single_choice' => 'Trắc nghiệm', 'true_false' => 'Đúng/Sai', 'true_false_group' => 'Đúng/Sai 4 ý',
        'short_answer' => 'Điền từ', 'multi_part' => 'Câu nhiều ý', 'programming' => 'Lập trình',
    ];

    /**
     * @return array{exam: array, files: array<string, UploadedFile>, answerKeys: array, codingItems: array, summary: array, tmpDir: string}
     *
     * @throws ValidationException
     */
    public static function parse(UploadedFile $zip): array
    {
        $za = new ZipArchive();
        if ($za->open($zip->getRealPath()) !== true) {
            self::fail(['Không mở được gói ZIP, kiểm tra lại tệp.']);
        }

        // exam.json ở gốc gói, hoặc nằm trong ĐÚNG 1 thư mục bọc ngoài (nén cả thư mục) — chấp nhận cả hai.
        $prefix = null;
        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = (string) $za->getNameIndex($i);
            if ($name === 'exam.json') {
                $prefix = '';
                break;
            }
            if ($prefix === null && preg_match('#^([^/]+/)exam\.json$#', $name, $m) && ! str_starts_with($name, '__MACOSX/')) {
                $prefix = $m[1];
            }
        }
        if ($prefix === null) {
            $za->close();
            self::fail(['Gói ZIP thiếu tệp exam.json ở gốc gói — đây có phải gói câu hỏi lẻ (question.json) không? Gói đề cần exam.json.']);
        }

        $json = json_decode((string) $za->getFromName($prefix.'exam.json'), true);
        if (! is_array($json)) {
            $za->close();
            self::fail(['exam.json không đúng định dạng JSON (thiếu dấu phẩy, ngoặc, hoặc dùng dấu nháy cong “ ”?).']);
        }
        if (! str_starts_with((string) ($json['schema'] ?? ''), self::SCHEMA_PREFIX)) {
            $za->close();
            self::fail(['exam.json phải có "schema": "OT360-EPACK/2.0".']);
        }
        if (! is_array($json['questions'] ?? null)) {
            $za->close();
            self::fail(['exam.json chưa có mục "questions" (danh sách câu). Gói dạng cũ 1.0 (answer_keys + coding) — vui lòng dùng file mẫu 2.0.']);
        }

        $errors = [];
        $examIn = is_array($json['exam'] ?? null) ? $json['exam'] : [];
        $title = trim((string) ($examIn['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'exam.json: thiếu "exam.title" (tên đề).';
        }

        [$previewFrom, $previewTo] = self::pageRange($examIn['preview_pages'] ?? null);
        $exam = [
            'title' => Str::limit($title, 255, ''),
            'subtitle' => self::str($examIn['subtitle'] ?? null, 255),
            'type' => (string) ($examIn['type'] ?? 'practice'), // SỬA 16/10 — mặc định đề Luyện tập
            'duration_minutes' => is_numeric($examIn['duration_minutes'] ?? null) ? max(0, (int) $examIn['duration_minutes']) : null,
            'publish_answer_rule' => in_array($examIn['publish_answer_rule'] ?? null, ['never', 'after_deadline', 'immediately'], true)
                ? $examIn['publish_answer_rule'] : 'after_deadline',
            'exam_code' => self::str($examIn['code'] ?? null, 60),
            'exam_category' => self::str($examIn['exam_category'] ?? null, 30),
            'exam_level' => self::str($examIn['exam_level'] ?? null, 10),
            'province' => self::str($examIn['province'] ?? null, 60),
            'academic_year' => self::str($examIn['academic_year'] ?? ($examIn['year'] ?? null), 20),
            'author' => self::str($examIn['author'] ?? null, 120),
            'difficulty_level' => is_numeric($examIn['difficulty'] ?? null) ? (int) $examIn['difficulty'] : null,
            'preview_page_from' => $previewFrom,
            'preview_page_to' => $previewTo,
        ];

        // ---- Tệp PDF / ảnh
        $tmpDir = rtrim(sys_get_temp_dir(), '/').'/ot360-exam-zip-'.Str::uuid();
        File::ensureDirectoryExists($tmpDir);
        $files = [];
        $names = is_array($json['files'] ?? null) ? $json['files'] : [];
        foreach (self::DEFAULT_FILES as $key => $default) {
            $entry = isset($names[$key]) && is_string($names[$key]) && $names[$key] !== '' ? $names[$key] : $default;
            $raw = $za->getFromName($prefix.ltrim($entry, '/'));
            if ($raw === false) {
                if (isset($names[$key]) && $names[$key] !== '') {
                    $errors[] = "exam.json khai tệp \"{$entry}\" (files.{$key}) nhưng trong gói không có tệp này.";
                }
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $isImage = $key === 'cover';
            if ($isImage ? ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) : $ext !== 'pdf') {
                $errors[] = "Tệp \"{$entry}\" phải là ".($isImage ? 'ảnh .jpg/.png/.webp' : 'tệp .pdf').'.';
                continue;
            }
            $path = $tmpDir.'/'.$key.'.'.$ext;
            file_put_contents($path, $raw);
            $mime = match ($ext) { 'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
            $files[$key] = new UploadedFile($path, basename($entry), $mime, null, true);
        }
        if (! isset($files['pdf'])) {
            $errors[] = 'Gói thiếu tệp đề "'.($names['pdf'] ?? self::DEFAULT_FILES['pdf']).'" (bắt buộc).';
        }

        // ---- Câu hỏi
        $answerKeys = [];
        $codingItems = [];
        $seenNo = [];
        $seenCode = [];
        $summary = array_fill_keys(array_keys(self::TYPE_LABELS), 0);

        foreach (array_values($json['questions']) as $idx => $item) {
            if (! is_array($item)) {
                $errors[] = 'Phần tử thứ '.($idx + 1).' trong "questions" không hợp lệ.';
                continue;
            }

            $no = is_numeric($item['no'] ?? null) ? (int) $item['no'] : $idx + 1;
            $where = "Câu {$no}";
            if ($no < 1) {
                $errors[] = "{$where}: \"no\" phải là số từ 1 trở lên.";
                continue;
            }
            if (isset($seenNo[$no])) {
                $errors[] = "{$where}: số câu bị trùng.";
                continue;
            }
            $seenNo[$no] = true;

            // Câu có thư mục riêng (QPACK): đọc question.json của thư mục.
            $folder = isset($item['folder']) && is_string($item['folder']) && trim($item['folder'], '/ ') !== '' ? trim($item['folder'], '/ ') : null;
            $q = null;
            if ($folder !== null) {
                $q = json_decode((string) $za->getFromName($prefix.$folder.'/question.json'), true);
                if (! is_array($q)) {
                    $errors[] = "{$where}: không đọc được \"{$folder}/question.json\" trong gói.";
                    continue;
                }
            }

            $type = (string) ($item['type'] ?? ($q['content']['type'] ?? ''));
            $grading = is_array($item['grading'] ?? null) ? $item['grading'] : (is_array($q['grading'] ?? null) ? $q['grading'] : []);
            $pointsRaw = $item['points'] ?? ($q['content']['points'] ?? null);
            if (! is_numeric($pointsRaw) || (float) $pointsRaw < 0) {
                $errors[] = "{$where}: thiếu \"points\" (điểm của câu) hoặc không phải số.";
                continue;
            }
            $points = round((float) $pointsRaw, 2);
            $pdfPage = is_numeric($item['pdf_page'] ?? null) && (int) $item['pdf_page'] >= 1 ? (int) $item['pdf_page'] : null;

            if ($type === 'programming') {
                if ($folder === null) {
                    $errors[] = "{$where}: câu Lập trình cần \"folder\" trỏ tới thư mục bài (question.json + tests/).";
                    continue;
                }
                $tests = self::readTests($za, $prefix.$folder.'/');
                if ($tests === []) {
                    $errors[] = "{$where}: thư mục \"{$folder}\" không có test hợp lệ (cần tests/01/ chứa 2 tệp input/output).";
                    continue;
                }
                $code = self::str($item['code'] ?? null, 40)
                    ?? Str::limit(preg_replace('/^\d+[-_.\s]*/', '', basename($folder)) ?: basename($folder), 40, '');
                $codeKey = mb_strtolower($code);
                if (isset($seenCode[$codeKey])) {
                    $errors[] = "{$where}: mã bài lập trình \"{$code}\" bị trùng với bài khác trong đề.";
                    continue;
                }
                $seenCode[$codeKey] = true;
                $g = is_array($q['grading'] ?? null) ? $q['grading'] : [];
                $codingItems[] = [
                    'no' => $no,
                    'code' => $code,
                    'title' => Str::limit(trim((string) ($item['title'] ?? ($q['content']['title'] ?? $code))) ?: $code, 255, ''),
                    'pdf_page' => $pdfPage,
                    'allowed_languages' => self::languages($g['languages'] ?? null),
                    'time_limit_ms' => is_numeric($g['time_limit_ms'] ?? null) ? max(100, min(60000, (int) $g['time_limit_ms'])) : 1000,
                    'memory_limit_kb' => is_numeric($g['memory_limit_mb'] ?? null) ? max(16384, min(1048576, (int) $g['memory_limit_mb'] * 1024)) : 262144,
                    'points' => $points,
                    'tests' => $tests,
                ];
                $summary['programming']++;
                continue;
            }

            [$answerType, $answer, $error] = self::answerFor($type, $grading);
            if ($error !== null) {
                $errors[] = "{$where}: {$error}";
                continue;
            }

            $answerKeys[] = ['question_no' => $no, 'question_type' => $answerType, 'correct_answer' => $answer, 'points' => $points];
            $summary[$answerType]++;
        }

        $za->close();

        if ($answerKeys === [] && $codingItems === [] && $errors === []) {
            $errors[] = 'Mục "questions" trong exam.json chưa có câu nào.';
        }

        if ($errors !== []) {
            File::deleteDirectory($tmpDir);
            self::fail($errors);
        }

        usort($answerKeys, fn ($a, $b) => $a['question_no'] <=> $b['question_no']);
        usort($codingItems, fn ($a, $b) => $a['no'] <=> $b['no']);

        return [
            'exam' => $exam,
            'files' => $files,
            'answerKeys' => $answerKeys,
            'codingItems' => $codingItems,
            'summary' => array_filter($summary),
            'tmpDir' => $tmpDir,
        ];
    }

    /** Xoá thư mục tạm chứa các tệp đã giải nén (gọi sau khi đã lưu xong). */
    public static function cleanup(array $parsed): void
    {
        if (! empty($parsed['tmpDir']) && is_dir($parsed['tmpDir'])) {
            File::deleteDirectory($parsed['tmpDir']);
        }
    }

    /** Một dòng tóm tắt cho thông báo sau khi nhập: "12 Trắc nghiệm · 2 Đúng/Sai · … · 2 Lập trình". */
    public static function summaryText(array $summary): string
    {
        $parts = [];
        foreach ($summary as $type => $count) {
            $parts[] = $count.' '.(self::TYPE_LABELS[$type] ?? $type);
        }

        return implode(' · ', $parts);
    }

    /**
     * Đổi 1 câu (type + grading kiểu QPACK) sang đáp án đúng của phiếu đề PDF (App\Enums\AnswerSheetQuestionType).
     *
     * @return array{0: ?string, 1: mixed, 2: ?string}  [question_type, correct_answer, lỗi]
     */
    private static function answerFor(string $type, array $grading): array
    {
        switch ($type) {
            case 'single_choice':
                $letter = strtoupper(trim((string) ($grading['correct_answer'] ?? '')));
                if (! preg_match('/^[A-Z]$/', $letter)) {
                    return [null, null, 'Trắc nghiệm cần "correct_answer" là 1 chữ cái (A, B, C, D…).'];
                }

                return [AnswerSheetQuestionType::SingleChoice->value, $letter, null];

            case 'true_false':
                $ca = $grading['correct_answer'] ?? null;
                if (is_array($ca)) {
                    $group = self::boolGroup(array_values($ca));
                    if ($group === null) {
                        return [null, null, 'Đúng/Sai nhiều ý cần "correct_answer" là danh sách true/false, ví dụ [true, false, true, false].'];
                    }

                    return [AnswerSheetQuestionType::TrueFalseGroup->value, $group, null];
                }
                $bool = self::toBool($ca);
                if ($bool === null) {
                    return [null, null, 'Đúng/Sai cần "correct_answer": true hoặc false (không có dấu ngoặc kép).'];
                }

                return [AnswerSheetQuestionType::TrueFalse->value, $bool, null];

            case 'short_answer':
                $accepted = self::acceptedAnswers($grading);
                if ($accepted === null) {
                    return [null, null, 'Điền từ cần "accepted_answers" — danh sách đáp án được chấp nhận, ví dụ ["8", "8.0"].'];
                }

                return [AnswerSheetQuestionType::ShortAnswer->value, $accepted, null];

            case 'composite':
                $parts = is_array($grading['parts'] ?? null) ? array_values($grading['parts']) : [];
                if ($parts === []) {
                    return [null, null, 'Câu nhiều ý cần "parts" — danh sách các ý a, b, c….'];
                }

                $allTrueFalse = true;
                foreach ($parts as $part) {
                    if (($part['response_type'] ?? '') !== 'true_false') {
                        $allTrueFalse = false;
                    }
                }

                // Toàn ý Đúng/Sai (2–4 ý) -> dạng "Đúng/Sai 4 ý" có sẵn của phiếu đáp án.
                if ($allTrueFalse && count($parts) >= 2 && count($parts) <= 4) {
                    $group = self::boolGroup(array_map(fn ($p) => $p['correct_answer'] ?? null, $parts));
                    if ($group === null) {
                        return [null, null, 'mỗi ý Đúng/Sai cần "correct_answer": true hoặc false.'];
                    }

                    return [AnswerSheetQuestionType::TrueFalseGroup->value, $group, null];
                }

                $out = [];
                foreach ($parts as $i => $part) {
                    $code = strtolower(trim((string) ($part['code'] ?? chr(97 + $i))));
                    if (! preg_match('/^[a-z]$/', $code) || isset($out[$code])) {
                        return [null, null, 'mỗi ý cần "code" là 1 chữ cái không trùng (a, b, c…).'];
                    }
                    $rt = (string) ($part['response_type'] ?? '');
                    if ($rt === 'single_choice') {
                        $letter = strtoupper(trim((string) ($part['correct_answer'] ?? '')));
                        if (! preg_match('/^[A-Z]$/', $letter)) {
                            return [null, null, "ý {$code} (trắc nghiệm) cần \"correct_answer\" là 1 chữ cái."];
                        }
                        $out[$code] = ['type' => AnswerSheetQuestionType::SingleChoice->value, 'value' => $letter];
                    } elseif ($rt === 'true_false') {
                        $bool = self::toBool($part['correct_answer'] ?? null);
                        if ($bool === null) {
                            return [null, null, "ý {$code} (đúng/sai) cần \"correct_answer\": true hoặc false."];
                        }
                        $out[$code] = ['type' => AnswerSheetQuestionType::TrueFalse->value, 'value' => $bool];
                    } elseif ($rt === 'short_answer') {
                        $accepted = self::acceptedAnswers($part);
                        if ($accepted === null) {
                            return [null, null, "ý {$code} (điền từ) cần \"accepted_answers\"."];
                        }
                        $out[$code] = ['type' => AnswerSheetQuestionType::ShortAnswer->value, 'value' => $accepted];
                    } else {
                        return [null, null, "ý {$code} có \"response_type\" chưa hỗ trợ — dùng single_choice, true_false hoặc short_answer."];
                    }
                }
                ksort($out);

                return [AnswerSheetQuestionType::MultiPart->value, $out, null];

            case '':
                return [null, null, 'thiếu "type" (single_choice, true_false, short_answer, composite, programming).'];

            default:
                return [null, null, "dạng \"{$type}\" chưa hỗ trợ — dùng single_choice, true_false, short_answer, composite hoặc programming."];
        }
    }

    /** accepted_answers (hoặc correct_answer) -> 1 chuỗi, nhiều đáp án ngăn bằng " | " (xem AssessmentAnswerKey::shortAnswerMatches). */
    private static function acceptedAnswers(array $grading): ?string
    {
        $list = $grading['accepted_answers'] ?? ($grading['correct_answer'] ?? null);
        $list = is_array($list) ? $list : ($list === null ? [] : [$list]);
        $clean = [];
        foreach ($list as $v) {
            if (is_scalar($v) && trim((string) $v) !== '') {
                $clean[] = trim(str_replace('|', '/', (string) $v));
            }
        }

        return $clean === [] ? null : implode(' | ', array_values(array_unique($clean)));
    }

    /** [true,false,…] -> ['a'=>true,'b'=>false,…] (tối đa 4 ý, đúng dạng "Đúng/Sai 4 ý"); null nếu sai dữ liệu. */
    private static function boolGroup(array $values): ?array
    {
        if ($values === [] || count($values) > 4) {
            return null;
        }
        $out = [];
        foreach ($values as $i => $v) {
            $b = self::toBool($v);
            if ($b === null) {
                return null;
            }
            $out[chr(97 + $i)] = $b;
        }

        return $out;
    }

    private static function toBool(mixed $v): ?bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 1 || $v === 0) {
            return $v === 1;
        }
        $s = mb_strtolower(trim((string) (is_scalar($v) ? $v : '')));

        return match ($s) {
            'true', '1', 'đ', 'đúng', 't' => true,
            'false', '0', 's', 'sai', 'f' => false,
            default => null,
        };
    }

    /** Ngôn ngữ cho phép của bài lập trình: cpp14/c++17/cpp… -> cpp, python3 -> python; không khai -> cả hai. */
    private static function languages(mixed $raw): array
    {
        $out = [];
        foreach ((array) $raw as $lang) {
            $l = strtolower((string) $lang);
            if (str_contains($l, 'py')) {
                $out['python'] = true;
            } elseif (str_contains($l, 'cpp') || str_contains($l, 'c++') || $l === 'c') {
                $out['cpp'] = true;
            }
        }

        return $out === [] ? ['cpp', 'python'] : array_keys($out);
    }

    /** tests/<số>/ chứa 1 tệp có chữ "input" và 1 tệp có chữ "output" trong tên — cùng luật với QuestionZipPackage. */
    private static function readTests(ZipArchive $za, string $base): array
    {
        $folders = [];
        $quoted = preg_quote($base, '#');
        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = (string) $za->getNameIndex($i);
            if (preg_match('#^'.$quoted.'tests/([^/]+)/([^/]+)$#i', $name, $m)) {
                $lower = strtolower($m[2]);
                if (str_contains($lower, 'input') || str_ends_with($lower, '.inp') || str_ends_with($lower, '.in')) {
                    $folders[$m[1]]['input'] = $name;
                } elseif (str_contains($lower, 'output') || str_ends_with($lower, '.out') || str_ends_with($lower, '.ans')) {
                    $folders[$m[1]]['output'] = $name;
                }
            }
        }
        ksort($folders, SORT_NATURAL);

        $tests = [];
        foreach ($folders as $pair) {
            if (! isset($pair['input'], $pair['output'])) {
                continue;
            }
            $in = $za->getFromName($pair['input']);
            $out = $za->getFromName($pair['output']);
            if ($in !== false && $out !== false) {
                $tests[] = ['input' => $in, 'output' => $out];
            }
        }

        return $tests;
    }

    /** "1" -> [1,1] · "1-2" -> [1,2] · không có -> [null,null]. */
    private static function pageRange(mixed $raw): array
    {
        if (is_numeric($raw) && (int) $raw >= 1) {
            return [1, (int) $raw];
        }
        if (is_string($raw) && preg_match('/^\s*(\d+)\s*[-–]\s*(\d+)\s*$/u', $raw, $m) && (int) $m[1] >= 1 && (int) $m[2] >= (int) $m[1]) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [null, null];
    }

    private static function str(mixed $v, int $max): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : Str::limit($s, $max, '');
    }

    /** @throws ValidationException */
    private static function fail(array $errors): never
    {
        $shown = array_slice($errors, 0, 12);
        if (count($errors) > 12) {
            $shown[] = '… và '.(count($errors) - 12).' lỗi khác.';
        }

        throw ValidationException::withMessages(['exam_zip' => $shown]);
    }
}

<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 3/11 (khách: "form zip vẫn cứ như mẫu MAU-CAU-HOI nhưng loại PHỔ THÔNG thì 1 bài nhiều câu — trắc nghiệm, điền từ…;
 * lập trình không ảnh hưởng") — đọc gói "Bài phổ thông". ĐÚNG khuôn gói câu hỏi OT360-QPACK (question.json + statement.pdf +
 * solution.pdf + assets/), chỉ khác:
 *
 *   content.type = "general" (hoặc "pho_thong")  → 1 bài NHIỀU câu, khai trong content.questions = [
 *       { "no": 1, "type": "single_choice", "points": 1, "title": "…" (tuỳ chọn), "prompt": "nội dung câu" (tuỳ chọn),
 *         "explanation": "lời giải ngắn" (tuỳ chọn), "assets": ["hinh-1"] (tuỳ chọn), "statement": "cau-1.pdf" (tuỳ chọn),
 *         "grading": { … y hệt mục grading của gói 1 câu … } }, … ]
 *     Dạng mỗi câu: single_choice | true_false | short_answer | composite (KHÔNG nhận programming).
 *     statement.pdf = đề CẢ BÀI (tab "Đề bài PDF"), solution.pdf = lời giải CẢ BÀI (hiện sau khi nộp).
 *
 *   content.type = single_choice / true_false / short_answer / composite (gói 1 câu cũ) → bài 1 câu.
 *   content.type = programming → báo dùng mục Lập trình (luồng cũ, không đụng).
 *
 * Mỗi câu được dựng lại thành 1 gói câu hỏi QPACK riêng rồi kiểm tra NGAY bằng QuestionZipPackage::parse() — đúng bộ đọc của
 * "Nhập câu hỏi từ ZIP" — để lúc tạo bài đi lại đúng hàm nhập câu hỏi sẵn có (admin / giáo viên). Lớp này chỉ ĐỌC + KIỂM TRA,
 * không ghi CSDL; gọi cleanup() khi xong.
 */
class GeneralSetZipPackage
{
    public const MULTI_TYPES = ['general', 'pho_thong'];

    /** Dạng câu được phép trong bài phổ thông. */
    public const ITEM_TYPES = ['single_choice', 'true_false', 'short_answer', 'composite'];

    public const TYPE_LABELS = [
        'single_choice' => 'Trắc nghiệm', 'true_false' => 'Đúng/Sai', 'short_answer' => 'Điền từ', 'composite' => 'Câu nhiều ý',
    ];

    /**
     * @return array{meta: array, files: array<string, UploadedFile>, questions: list<array{no:int, points:float, zip:UploadedFile, contentType:string, prompt:?string, explanation:?string}>, summary: array<string,int>, tmpDir: string}
     *
     * @throws ValidationException
     */
    public static function parse(UploadedFile $zip): array
    {
        $za = new ZipArchive();
        if ($za->open($zip->getRealPath()) !== true) {
            self::fail(['Không mở được gói ZIP, kiểm tra lại tệp.']);
        }

        // question.json ở gốc gói, hoặc trong ĐÚNG 1 thư mục bọc ngoài (nén cả thư mục) — nhận cả hai.
        $prefix = null;
        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = (string) $za->getNameIndex($i);
            if ($name === 'question.json') {
                $prefix = '';
                break;
            }
            if ($prefix === null && preg_match('#^([^/]+/)question\.json$#', $name, $m) && ! str_starts_with($name, '__MACOSX/')) {
                $prefix = $m[1];
            }
        }
        if ($prefix === null) {
            $za->close();
            self::fail(['Gói ZIP thiếu tệp question.json ở gốc gói (đúng mẫu MAU-CAU-HOI).']);
        }

        $json = json_decode((string) $za->getFromName($prefix.'question.json'), true);
        if (! is_array($json)) {
            $za->close();
            self::fail(['question.json không đúng định dạng JSON (thiếu dấu phẩy, ngoặc, hoặc dùng dấu nháy cong “ ”?).']);
        }
        if (! str_starts_with((string) ($json['schema'] ?? ''), 'OT360-QPACK')) {
            $za->close();
            self::fail(['question.json phải có "schema": "OT360-QPACK/1.0" như file mẫu.']);
        }

        $content = is_array($json['content'] ?? null) ? $json['content'] : [];
        $type = strtolower(trim((string) ($content['type'] ?? '')));
        if ($type === 'programming') {
            $za->close();
            self::fail(['Gói này là bài LẬP TRÌNH — nhập ở mục "Lập trình / câu lẻ" như trước. Mục Phổ thông nhận trắc nghiệm, đúng/sai, điền từ, câu nhiều ý.']);
        }
        $multi = in_array($type, self::MULTI_TYPES, true);
        if (! $multi && ! in_array($type, self::ITEM_TYPES, true)) {
            $za->close();
            self::fail(['content.type phải là "general" (bài phổ thông nhiều câu) hoặc 1 dạng câu: '.implode(', ', self::ITEM_TYPES).'.']);
        }

        $errors = [];
        $title = trim((string) ($content['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'question.json: thiếu "content.title" (tên bài).';
        }

        $base = pathinfo($zip->getClientOriginalName(), PATHINFO_FILENAME);
        $code = self::cleanCode((string) ($content['code'] ?? '') ?: $base);

        $tmpDir = rtrim(sys_get_temp_dir(), '/').'/ot360-general-zip-'.Str::uuid();
        File::ensureDirectoryExists($tmpDir);

        // Tệp chung của bài: đề cả bài + lời giải cả bài (chỉ với gói nhiều câu; gói 1 câu thì 2 tệp này thuộc về chính câu đó).
        $files = [];
        if ($multi) {
            foreach (['statement' => 'statement.pdf', 'solution' => 'solution.pdf'] as $key => $entry) {
                $raw = $za->getFromName($prefix.$entry);
                if ($raw === false) {
                    continue;
                }
                $path = $tmpDir.'/bai-'.$key.'.pdf';
                file_put_contents($path, $raw);
                $files[$key] = new UploadedFile($path, $entry, 'application/pdf', null, true);
            }
        }

        $rootAssets = [];
        foreach ((array) ($json['assets'] ?? []) as $asset) {
            if (is_array($asset) && isset($asset['id']) && is_string($asset['id'])) {
                $rootAssets[$asset['id']] = $asset;
            }
        }

        $items = $multi ? (is_array($content['questions'] ?? null) ? array_values($content['questions']) : null) : [];
        if ($multi && ($items === null || $items === [])) {
            $errors[] = 'question.json: bài phổ thông cần mục "content.questions" (danh sách câu), hiện chưa có câu nào.';
            $items = [];
        }

        $questions = [];
        $summary = [];
        $seenNo = [];

        if (! $multi) {
            // Gói 1 câu cũ → bài 1 câu: dùng NGUYÊN gói làm gói câu hỏi.
            $qPath = $tmpDir.'/q-1.zip';
            copy($zip->getRealPath(), $qPath);
            $qUpload = new UploadedFile($qPath, $code.'.zip', 'application/zip', null, true);
            $err = self::checkGrading($type, (array) ($json['grading'] ?? []));
            if ($err !== null) {
                $errors[] = 'Câu 1: '.$err;
            } else {
                try {
                    QuestionZipPackage::parse($qUpload);
                    $questions[] = [
                        'no' => 1, 'points' => round(max(0, (float) ($content['points'] ?? 1)), 2), 'zip' => $qUpload, 'contentType' => $type,
                        'prompt' => null, 'explanation' => self::str($content['explanation'] ?? null, 5000),
                    ];
                    $summary[$type] = 1;
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $messages) {
                        foreach ((array) $messages as $message) {
                            $errors[] = 'Câu 1: '.$message;
                        }
                    }
                }
            }
        }

        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                $errors[] = 'Phần tử thứ '.($idx + 1).' trong "content.questions" không hợp lệ.';
                continue;
            }
            $no = is_numeric($item['no'] ?? null) ? (int) $item['no'] : $idx + 1;
            $where = "Câu {$no}";
            if ($no < 1 || isset($seenNo[$no])) {
                $errors[] = "{$where}: số câu ".($no < 1 ? 'phải từ 1 trở lên.' : 'bị trùng.');
                continue;
            }
            $seenNo[$no] = true;

            $itemType = strtolower(trim((string) ($item['type'] ?? '')));
            if ($itemType === 'programming') {
                $errors[] = "{$where}: bài phổ thông không chứa câu lập trình (nhập bài lập trình ở mục Lập trình).";
                continue;
            }
            if (! in_array($itemType, self::ITEM_TYPES, true)) {
                $errors[] = "{$where}: \"type\" phải là một trong: ".implode(', ', self::ITEM_TYPES).'.';
                continue;
            }
            $pointsRaw = $item['points'] ?? 1;
            if (! is_numeric($pointsRaw) || (float) $pointsRaw < 0) {
                $errors[] = "{$where}: \"points\" (điểm của câu) phải là số không âm.";
                continue;
            }
            $grading = is_array($item['grading'] ?? null) ? $item['grading'] : [];
            $err = self::checkGrading($itemType, $grading);
            if ($err !== null) {
                $errors[] = "{$where}: {$err}";
                continue;
            }

            // Dựng gói câu hỏi QPACK riêng cho câu này (cùng taxonomy / pedagogy / attribution của bài).
            $pedagogy = is_array($json['pedagogy'] ?? null) ? $json['pedagogy'] : [];
            if (isset($item['difficulty'])) {
                $pedagogy['difficulty'] = $item['difficulty'];
            }
            $assetIds = array_values(array_filter(array_map('strval', (array) ($item['assets'] ?? ($item['asset'] ?? [])))));
            $assets = [];
            $qJson = [
                'schema' => (string) $json['schema'],
                'package_language' => $json['package_language'] ?? 'vi-VN',
                'content' => [
                    'title' => Str::limit(trim((string) ($item['title'] ?? '')) ?: ($title.' — Câu '.$no), 255, ''),
                    'type' => $itemType,
                    'points' => (float) $pointsRaw,
                ],
                'taxonomy' => $json['taxonomy'] ?? null,
                'pedagogy' => $pedagogy,
                'attribution' => $json['attribution'] ?? null,
                'assets' => [],
                'grading' => $grading,
                'extensions' => (object) [],
            ];

            $qPath = $tmpDir.'/q-'.$no.'.zip';
            $qz = new ZipArchive();
            $qz->open($qPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($assetIds as $aid) {
                $decl = $rootAssets[$aid] ?? null;
                $raw = is_array($decl) && is_string($decl['path'] ?? null) ? $za->getFromName($prefix.ltrim($decl['path'], '/')) : false;
                if ($raw === false) {
                    $errors[] = "{$where}: ảnh \"{$aid}\" chưa khai trong mục \"assets\" của question.json hoặc không có tệp trong gói.";
                    continue;
                }
                $qz->addFromString(ltrim($decl['path'], '/'), $raw);
                $assets[] = $decl;
            }
            $qJson['assets'] = $assets;
            foreach (['statement' => 'statement.pdf', 'solution' => 'solution.pdf'] as $key => $target) {
                $entry = $item[$key] ?? null;
                if (! is_string($entry) || trim($entry) === '') {
                    continue;
                }
                $raw = $za->getFromName($prefix.ltrim($entry, '/'));
                if ($raw === false) {
                    $errors[] = "{$where}: khai \"{$key}\": \"{$entry}\" nhưng trong gói không có tệp này.";
                    continue;
                }
                $qz->addFromString($target, $raw);
            }
            $qz->addFromString('question.json', json_encode($qJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $qz->close();

            $qUpload = new UploadedFile($qPath, $code.'-C'.$no.'.zip', 'application/zip', null, true);
            try {
                QuestionZipPackage::parse($qUpload);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $messages) {
                    foreach ((array) $messages as $message) {
                        $errors[] = "{$where}: {$message}";
                    }
                }
                continue;
            }

            $questions[] = [
                'no' => $no, 'points' => round((float) $pointsRaw, 2), 'zip' => $qUpload, 'contentType' => $itemType,
                'prompt' => self::str($item['prompt'] ?? null, 10000),
                'explanation' => self::str($item['explanation'] ?? null, 5000),
            ];
            $summary[$itemType] = ($summary[$itemType] ?? 0) + 1;
        }

        $za->close();

        if ($errors !== []) {
            File::deleteDirectory($tmpDir);
            self::fail($errors);
        }

        usort($questions, fn ($a, $b) => $a['no'] <=> $b['no']);

        $taxonomy = is_array($json['taxonomy'] ?? null) ? $json['taxonomy'] : [];
        $meta = [
            'title' => Str::limit($title, 255, ''),
            'subtitle' => self::str($content['description'] ?? ($content['subtitle'] ?? null), 255),
            'code' => $code,
            'difficulty_level' => ($dk = QuestionDifficulty::normalize($json['pedagogy']['difficulty'] ?? null)) !== null ? QuestionDifficulty::stars($dk) : null,
            'author' => self::str($json['attribution']['source_citation'] ?? null, 120),
            'academic_year' => self::str($taxonomy['exam_year'] ?? null, 20),
            'province' => self::str($taxonomy['province'] ?? null, 60),
        ];

        return ['meta' => $meta, 'files' => $files, 'questions' => $questions, 'summary' => $summary, 'tmpDir' => $tmpDir];
    }

    /** "5 Trắc nghiệm · 2 Đúng/Sai · 1 Điền từ". */
    public static function summaryText(array $summary): string
    {
        $parts = [];
        foreach (self::TYPE_LABELS as $t => $label) {
            if (! empty($summary[$t])) {
                $parts[] = $summary[$t].' '.$label;
            }
        }

        return implode(' · ', $parts);
    }

    public static function cleanup(array $parsed): void
    {
        if (! empty($parsed['tmpDir'])) {
            File::deleteDirectory($parsed['tmpDir']);
        }
    }

    /** Kiểm tra đáp án tối thiểu theo dạng — không bao giờ đoán bừa đáp án (đoán sai là chấm sai cho cả lớp). */
    private static function checkGrading(string $type, array $g): ?string
    {
        switch ($type) {
            case 'single_choice':
                $choices = is_array($g['choices'] ?? null) ? array_values($g['choices']) : [];
                if (count($choices) < 2) {
                    return 'trắc nghiệm cần ít nhất 2 phương án trong "grading.choices".';
                }
                $ids = array_map(fn ($c) => is_array($c) ? ($c['id'] ?? null) : null, $choices);
                if (! in_array($g['correct_answer'] ?? null, $ids, true)) {
                    return '"grading.correct_answer" phải là id của 1 phương án (vd "B").';
                }

                return null;
            case 'true_false':
                return is_bool($g['correct_answer'] ?? null) ? null : '"grading.correct_answer" phải là true hoặc false (không có dấu ngoặc kép).';
            case 'short_answer':
                $acc = array_filter(array_map(fn ($v) => trim((string) $v), (array) ($g['accepted_answers'] ?? [])), fn ($v) => $v !== '');

                return $acc !== [] ? null : 'điền từ cần ít nhất 1 đáp án trong "grading.accepted_answers".';
            case 'composite':
                return is_array($g['parts'] ?? null) && $g['parts'] !== [] ? null : 'câu nhiều ý cần danh sách ý trong "grading.parts".';
        }

        return 'dạng câu không hỗ trợ.';
    }

    private static function cleanCode(string $raw): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii(trim($raw))) ?: 'BAI';

        return Str::limit(trim($code, '_'), 60, '') ?: 'BAI';
    }

    private static function str(mixed $v, int $max): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : Str::limit($s, $max, '');
    }

    private static function fail(array $errors): never
    {
        throw ValidationException::withMessages(['general_zip' => array_values(array_unique($errors))]);
    }
}

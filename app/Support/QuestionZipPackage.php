<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 18/9 (khách: "tạo câu hỏi chỗ giáo viên cũng cho nhập file zip như admin luôn nha") —
 * GỘP về 1 nơi duy nhất phần ĐỌC gói ZIP OT360-QPACK (mở zip, kiểm tra question.json, gom test
 * case/tệp đính kèm/asset) + map content.type sang Question::type + dựng grading_config theo
 * từng loại. Trước đây phần này nằm trong App\Services\Admin\ContentService (bản ĐẦY ĐỦ, 5 loại
 * nội dung, có assets) và một bản CHÉP TAY CŨ trong App\Services\Teacher\QuestionService (chỉ
 * 'programming', không có assets) — nên giáo viên không nhập được gói trắc nghiệm/điền khuyết
 * dù admin nhập được. Cả 2 service giờ gọi CHUNG lớp này, không còn 2 bản lệch nhau.
 *
 * Lớp này CỐ Ý thuần logic (static, không đụng DB/Storage): phần LƯU tệp xuống disk + tạo bản
 * ghi Question vẫn nằm ở từng service vì admin lưu vào "Kho chung" còn giáo viên lưu vào kho
 * riêng của mình (owner_type/bank/mã câu hỏi khác nhau) — xem questionStoreFromZipPackage()
 * (admin) và storeFromZipPackage() (giáo viên).
 */
class QuestionZipPackage
{
    /**
     * SỬA 31/8 (2, "mở rộng ZIP bài tập" — không chỉ lập trình): 5 dạng nội dung (content.type)
     * hiện được hỗ trợ — trước đây CHỈ 'programming'. Khách chốt: essay (phần tự luận trong
     * 'composite') ghi nhận, chưa tự chấm được (cùng cách xử lý câu Lập trình).
     */
    public const SUPPORTED_CONTENT_TYPES = ['programming', 'single_choice', 'true_false', 'short_answer', 'composite'];

    /**
     * Mở gói ZIP, đọc + kiểm tra question.json (phải đúng schema "OT360-QPACK" và content.type
     * thuộc SUPPORTED_CONTENT_TYPES ở trên), gom test case từ các thư mục tests/<số>/ (mỗi thư
     * mục có 1 tệp chứa "input" trong tên và 1 tệp chứa "output" trong tên — KHÔNG cố định đúng
     * tên "INPUT.INP"/"OUTPUT.OUT" vì gói khác có thể đặt tên file khác), đọc nội dung 3 tệp
     * đính kèm cố định (statement.pdf/solution.pdf/reference/official.cpp) nếu có, VÀ đọc luôn
     * 'assets' (audio/ảnh... khai báo trong question.json, path bất kỳ trong gói) — đọc hết
     * NGAY TRONG hàm này rồi đóng ZIP luôn, tránh phải giữ ZipArchive mở vắt qua nhiều hàm.
     *
     * Test case (tests/<số>/) chỉ BẮT BUỘC với content.type = 'programming' (chấm bằng so khớp
     * input/output qua judge) — 4 dạng còn lại chấm qua 'grading' trong question.json.
     *
     * @return array{json: array, testCases: array<int, array{input:string, output:string}>, attachments: array<string, array{content:string, filename:string}>, assets: array<int, array{id:string, kind:string, filename:string, content:string, transcript:?string, alt_text:?string}>}
     *
     * @throws ValidationException nếu gói ZIP không mở được, thiếu/sai question.json, hoặc câu
     *                             lập trình không có test case hợp lệ nào.
     */
    public static function parse(UploadedFile $zip): array
    {
        $zipArchive = new ZipArchive();
        if ($zipArchive->open($zip->getRealPath()) !== true) {
            throw ValidationException::withMessages(['zip_package' => 'Không mở được gói ZIP, kiểm tra lại tệp.']);
        }

        $jsonRaw = $zipArchive->getFromName('question.json');
        if ($jsonRaw === false) {
            $zipArchive->close();
            throw ValidationException::withMessages(['zip_package' => 'Gói ZIP thiếu tệp question.json ở gốc.']);
        }

        $json = json_decode($jsonRaw, true);
        if (! is_array($json)) {
            $zipArchive->close();
            throw ValidationException::withMessages(['zip_package' => 'question.json trong gói ZIP không đúng định dạng JSON.']);
        }

        $schema = (string) ($json['schema'] ?? '');
        $contentType = (string) ($json['content']['type'] ?? '');
        if (! str_starts_with($schema, 'OT360-QPACK') || ! in_array($contentType, self::SUPPORTED_CONTENT_TYPES, true)) {
            $zipArchive->close();
            throw ValidationException::withMessages([
                'zip_package' => 'Gói ZIP không đúng định dạng OT360-QPACK hoặc loại nội dung (content.type) chưa được hỗ trợ.',
            ]);
        }

        $attachmentNames = ['statement.pdf' => 'statement', 'solution.pdf' => 'solution', 'reference/official.cpp' => 'reference'];
        $attachments = [];
        $testFolders = [];

        // Gom trước danh sách đường dẫn asset cần đọc (path -> true) từ question.json['assets']
        // — đọc luôn trong CÙNG vòng lặp quét zip bên dưới, không mở lại ZipArchive lần 2.
        $assetPaths = [];
        foreach (($json['assets'] ?? []) as $asset) {
            if (isset($asset['path']) && is_string($asset['path'])) {
                $assetPaths[$asset['path']] = true;
            }
        }
        $assetsRaw = [];

        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $name = $zipArchive->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue; // thư mục con trong zip, bỏ qua
            }

            if (isset($attachmentNames[$name])) {
                $raw = $zipArchive->getFromName($name);
                if ($raw !== false) {
                    $attachments[$attachmentNames[$name]] = ['content' => $raw, 'filename' => basename($name)];
                }

                continue;
            }

            if (isset($assetPaths[$name])) {
                $raw = $zipArchive->getFromName($name);
                if ($raw !== false) {
                    $assetsRaw[$name] = $raw;
                }

                continue;
            }

            if (preg_match('#^tests/([^/]+)/([^/]+)$#i', $name, $m)) {
                $lower = strtolower($m[2]);
                if (str_contains($lower, 'input')) {
                    $testFolders[$m[1]]['input'] = $name;
                } elseif (str_contains($lower, 'output')) {
                    $testFolders[$m[1]]['output'] = $name;
                }
            }
        }

        ksort($testFolders, SORT_NATURAL);
        $testCases = [];
        foreach ($testFolders as $pair) {
            if (! isset($pair['input'], $pair['output'])) {
                continue; // thiếu 1 trong 2 vế — không đoán bừa, bỏ qua thư mục test này
            }

            $input = $zipArchive->getFromName($pair['input']);
            $output = $zipArchive->getFromName($pair['output']);
            if ($input === false || $output === false) {
                continue;
            }

            $testCases[] = ['input' => $input, 'output' => $output];
        }

        $zipArchive->close();

        if ($contentType === 'programming' && $testCases === []) {
            throw ValidationException::withMessages([
                'zip_package' => 'Không tìm thấy test case hợp lệ trong gói ZIP (cần thư mục tests/<số>/ chứa 2 tệp input/output).',
            ]);
        }

        // Ghép lại 'assets' đầy đủ (metadata khai báo trong question.json + nội dung nhị phân
        // vừa đọc được) — asset khai báo trong JSON nhưng KHÔNG tìm thấy file thật trong zip bị
        // bỏ qua (không đoán bừa/không chặn cả gói chỉ vì 1 asset lỗi).
        $assets = [];
        foreach (($json['assets'] ?? []) as $asset) {
            $path = $asset['path'] ?? null;
            if (! is_string($path) || ! isset($assetsRaw[$path])) {
                continue;
            }

            $assets[] = [
                'id' => (string) ($asset['id'] ?? Str::uuid()),
                'kind' => (string) ($asset['kind'] ?? 'file'),
                'filename' => basename($path),
                'content' => $assetsRaw[$path],
                'transcript' => $asset['transcript'] ?? null,
                'alt_text' => $asset['alt_text'] ?? null,
            ];
        }

        return ['json' => $json, 'testCases' => $testCases, 'attachments' => $attachments, 'assets' => $assets];
    }

    /**
     * SỬA 31/8 (2) — map content.type (gói ZIP) sang Question::type (cột 'type' thật của hệ
     * thống): 'single_choice'/'true_false' quy về 'mcq' — TÁI DÙNG NGUYÊN VẸN máy Mcq đã có
     * (QuestionGrader::isMcqCorrect(), màn Luyện tập/Làm bài Mcq) thay vì viết thêm luồng
     * chấm/hiển thị riêng, xem choiceGradingConfig()/trueFalseGradingConfig() bên dưới;
     * 'short_answer' quy về 'fill_blank' cùng lý do. 'composite' là loại DUY NHẤT thật sự mới
     * (nhiều phần khác dạng, không quy về đâu được).
     */
    public static function questionType(string $contentType): string
    {
        return match ($contentType) {
            'programming' => 'coding',
            'single_choice', 'true_false' => 'mcq',
            'short_answer' => 'fill_blank',
            'composite' => 'composite',
            default => 'coding',
        };
    }

    /**
     * Dựng grading_config từ gói ZIP theo ĐÚNG content.type — khác buildGradingConfig() trong 2
     * service (dựng từ input FORM nhập tay, cấu trúc khác hẳn cấu trúc JSON của gói ZIP) nên
     * tách hàm riêng, không gộp chung tránh 1 hàm phải hiểu 2 nguồn dữ liệu khác hình dạng.
     *
     * @param  array<int, array{input:string, output:string}>  $testCases
     * @return array<string, mixed>
     */
    public static function gradingConfig(string $contentType, array $json, array $testCases): array
    {
        $grading = $json['grading'] ?? [];

        return match ($contentType) {
            'programming' => array_filter([
                'test_cases' => $testCases,
                // filled() y hệt buildGradingConfig('coding') của 2 service (giá trị rỗng ->
                // null -> bị array_filter loại bỏ), giữ nguyên hành vi cũ khi gói khai rỗng.
                'time_limit_ms' => filled($grading['time_limit_ms'] ?? 1000) ? (int) ($grading['time_limit_ms'] ?? 1000) : null,
                'memory_limit_mb' => filled($grading['memory_limit_mb'] ?? 256) ? (int) ($grading['memory_limit_mb'] ?? 256) : null,
                // SỬA 24/8 — 3 khoá dưới đây CHỈ được gói ZIP điền (form nhập tay không có
                // trường tương ứng) — giữ lại trong grading_config để dành cho khi có judge
                // chấm code thật sau này (ngôn ngữ cho phép / quy ước tên file input-output /
                // chấm theo nhóm điểm subtasks). Không dùng ở đâu khác hiện tại — vô hại.
                'languages' => $grading['languages'] ?? null,
                'file_io' => $grading['file_io'] ?? null,
                'subtasks' => $json['subtasks'] ?? null,
            ], fn ($v) => $v !== null),
            'single_choice' => self::choiceGradingConfig($grading),
            'true_false' => self::trueFalseGradingConfig($grading),
            'short_answer' => self::shortAnswerGradingConfig($grading),
            'composite' => self::compositeGradingConfig($grading),
            default => [],
        };
    }

    /**
     * ZIP single_choice: grading.choices = [{id,text}] (thứ tự = thứ tự hiện), grading.
     * correct_answer = id chữ cái (vd "B"). Map sang ĐÚNG cấu trúc Mcq hiện có (options: mảng
     * text theo thứ tự, correct_options: mảng CHỈ SỐ — xem QuestionGrader::isMcqCorrect()) để
     * dùng lại NGUYÊN VẸN toàn bộ máy Mcq đã có, không viết thêm UI/luồng chấm riêng.
     */
    private static function choiceGradingConfig(array $grading): array
    {
        $choices = $grading['choices'] ?? [];
        $options = array_values(array_map(fn ($c) => (string) ($c['text'] ?? ''), $choices));

        $correctId = $grading['correct_answer'] ?? null;
        $correctIndex = null;
        foreach (array_values($choices) as $i => $c) {
            if (($c['id'] ?? null) === $correctId) {
                $correctIndex = $i;
                break;
            }
        }

        return [
            'options' => $options,
            'correct_options' => $correctIndex !== null ? [$correctIndex] : [],
        ];
    }

    /**
     * ZIP true_false: chỉ có grading.correct_answer (bool), KHÔNG có 'choices' — map sang Mcq 2
     * phương án cố định "Đúng"/"Sai", cùng lý do tái dùng máy Mcq như single_choice ở trên.
     */
    private static function trueFalseGradingConfig(array $grading): array
    {
        $correct = (bool) ($grading['correct_answer'] ?? false);

        return [
            'options' => ['Đúng', 'Sai'],
            'correct_options' => [$correct ? 0 : 1],
        ];
    }

    /**
     * ZIP short_answer: grading.accepted_answers + grading.normalization {trim, case_sensitive,
     * remove_diacritics} -> khớp thẳng cấu trúc FillBlank hiện có, thêm key 'remove_diacritics'
     * (QuestionGrader::isFillBlankCorrect() đã hỗ trợ đọc, xem SỬA 31/8 (2) ở đó).
     */
    private static function shortAnswerGradingConfig(array $grading): array
    {
        $normalization = $grading['normalization'] ?? [];

        return [
            'accepted_answers' => array_values(array_map('strval', $grading['accepted_answers'] ?? [])),
            'case_sensitive' => (bool) ($normalization['case_sensitive'] ?? false),
            'remove_diacritics' => (bool) ($normalization['remove_diacritics'] ?? false),
        ];
    }

    /**
     * ZIP composite: grading.mode = 'per_part', grading.parts = [{code, response_type, points,
     * ...}] — GIỮ NGUYÊN cấu trúc gốc của gói ZIP (KHÔNG quy về Mcq/FillBlank như 3 loại trên)
     * vì mỗi phần (part) có thể khác response_type nhau trong CÙNG 1 câu — không có 1 kiểu
     * chấm/hiển thị chung nào để quy về. Student\PracticeByQuestionService::gradeCompositeParts()
     * đọc thẳng cấu trúc này để chấm từng phần lúc "Làm bài". Chuẩn hoá tối thiểu (đảm bảo có
     * 'points' số) để tránh lỗi truy cập khoá không tồn tại về sau.
     */
    private static function compositeGradingConfig(array $grading): array
    {
        $parts = array_map(function (array $part) {
            $part['points'] = (float) ($part['points'] ?? 0);

            return $part;
        }, $grading['parts'] ?? []);

        return [
            'mode' => 'per_part',
            'parts' => array_values($parts),
        ];
    }

    /**
     * SỬA 18/9 — độ khó khai báo sẵn trong gói ZIP (pedagogy.difficulty: "easy"/"medium"/
     * "hard"/"expert"). Trả null nếu gói không khai (hoặc khai giá trị lạ) — khi đó trang Luyện
     * tập public tự suy độ khó theo điểm như trước, xem Public\PracticeService::problemRows().
     */
    public static function difficultyFrom(array $json): ?string
    {
        $value = $json['pedagogy']['difficulty'] ?? null;

        return is_string($value) && in_array($value, ['easy', 'medium', 'hard', 'expert'], true) ? $value : null;
    }
}

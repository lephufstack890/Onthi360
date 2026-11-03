<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 3/11 (khách: "tab Phổ thông thì form Phổ thông — file nhập như nào thì form như vậy") — đóng FORM NHẬP TAY "Bài phổ thông"
 * (partials/general-set-form) thành ĐÚNG 1 gói ZIP Phổ thông (question.json + statement.pdf + solution.pdf + assets/) rồi để
 * GeneralSetImportService nhập như file người dùng tải lên. Nhờ vậy form và file dùng CHUNG một luật kiểm tra / chia điểm / chấm.
 * Lớp này chỉ dựng gói (không ghi CSDL); gọi cleanup() khi xong.
 */
class GeneralSetFormPackage
{
    private const PART_TYPES = ['true_false', 'short_answer', 'single_choice'];

    /**
     * @param  array<string, mixed>  $data  dữ liệu form đã validate
     * @param  array<string, UploadedFile>  $images  ảnh theo uid câu
     * @return array{zip: UploadedFile, tmpDir: string}
     *
     * @throws ValidationException
     */
    public static function build(array $data, ?UploadedFile $statement, ?UploadedFile $solution, array $images): array
    {
        $raw = json_decode((string) ($data['questions_json'] ?? ''), true);
        if (! is_array($raw) || $raw === []) {
            self::fail(['Bài phổ thông cần ít nhất 1 câu.']);
        }
        if (count($raw) > 200) {
            self::fail(['Một bài tối đa 200 câu.']);
        }

        $tmpDir = rtrim(sys_get_temp_dir(), '/').'/ot360-general-form-'.Str::uuid();
        File::ensureDirectoryExists($tmpDir);

        $assets = [];
        $assetFiles = [];
        $questions = [];
        foreach (array_values($raw) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = self::cleanItem($item, $i + 1);

            $uid = is_string($item['uid'] ?? null) ? $item['uid'] : '';
            $img = $uid !== '' ? ($images[$uid] ?? null) : null;
            if ($img instanceof UploadedFile && $img->isValid()) {
                $ext = strtolower($img->getClientOriginalExtension() ?: $img->guessExtension() ?: 'png');
                $id = 'hinh-cau-'.($i + 1);
                $path = 'assets/'.$id.'.'.preg_replace('/[^a-z0-9]/', '', $ext);
                $assets[] = ['id' => $id, 'kind' => 'image', 'path' => $path, 'alt_text' => 'Hình minh hoạ câu '.($i + 1)];
                $assetFiles[$path] = $img->getRealPath();
                $q['assets'] = [$id];
            }
            $questions[] = $q;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $grades = array_values(array_unique(array_filter(array_map('intval', (array) ($data['grades'] ?? [])), fn ($g) => $g >= 1 && $g <= 12)));
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? ''))), fn ($t) => $t !== ''));

        $content = [
            'title' => $title,
            'type' => 'general',
            'questions' => $questions,
        ];
        if ($code !== '') {
            $content['code'] = $code;
        }
        if (filled($data['description'] ?? null)) {
            $content['description'] = trim((string) $data['description']);
        }
        if ($statement) {
            $content['presentation'] = ['mode' => 'pdf', 'file' => 'statement.pdf'];
        }
        if ($solution) {
            $content['solution'] = ['file' => 'solution.pdf', 'release_policy' => 'after_submission'];
        }

        $json = [
            'schema' => 'OT360-QPACK/1.0',
            'package_language' => 'vi-VN',
            'content' => $content,
            'taxonomy' => array_filter([
                'subject' => $subject !== '' ? ['code' => $subject, 'name' => SubjectCatalog::SUBJECTS[$subject] ?? $subject] : null,
                'grade_levels' => $grades,
                'topic_ids' => [],
                'tags' => $tags,
                'keywords' => [],
                'province' => filled($data['province'] ?? null) ? (string) $data['province'] : null,
                'exam_year' => filled($data['exam_year'] ?? null) ? (int) $data['exam_year'] : null,
            ], fn ($v) => $v !== null),
            'pedagogy' => array_filter(['difficulty' => filled($data['difficulty'] ?? null) ? (string) $data['difficulty'] : null], fn ($v) => $v !== null),
            'attribution' => array_filter(['source_citation' => filled($data['source'] ?? null) ? trim((string) $data['source']) : null], fn ($v) => $v !== null),
            'assets' => $assets,
            'extensions' => (object) [],
        ];

        $zipName = (Str::slug($code !== '' ? $code : $title) ?: 'bai-pho-thong').'.zip';
        $zipPath = $tmpDir.'/'.$zipName;
        $za = new ZipArchive();
        if ($za->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($tmpDir);
            self::fail(['Không tạo được gói tạm trên máy chủ, thử lại sau.']);
        }
        $za->addFromString('question.json', json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        if ($statement) {
            $za->addFile($statement->getRealPath(), 'statement.pdf');
        }
        if ($solution) {
            $za->addFile($solution->getRealPath(), 'solution.pdf');
        }
        foreach ($assetFiles as $path => $real) {
            $za->addFile($real, $path);
        }
        $za->close();

        return ['zip' => new UploadedFile($zipPath, $zipName, 'application/zip', null, true), 'tmpDir' => $tmpDir];
    }

    public static function cleanup(?array $built): void
    {
        if (! empty($built['tmpDir'])) {
            File::deleteDirectory($built['tmpDir']);
        }
    }

    /** Lọc 1 câu về ĐÚNG khuôn content.questions[] — chỉ giữ các khoá gói ZIP hiểu. */
    private static function cleanItem(array $item, int $no): array
    {
        $type = (string) ($item['type'] ?? '');
        $g = is_array($item['grading'] ?? null) ? $item['grading'] : [];
        $q = ['no' => $no, 'type' => $type, 'points' => 1];
        foreach (['title' => 255, 'prompt' => 10000, 'explanation' => 5000] as $key => $max) {
            $v = trim((string) ($item[$key] ?? ''));
            if ($v !== '') {
                $q[$key] = Str::limit($v, $max, '');
            }
        }

        $q['grading'] = match ($type) {
            'single_choice' => [
                'choices' => array_values(array_map(fn ($c) => ['id' => (string) ($c['id'] ?? ''), 'text' => Str::limit(trim((string) ($c['text'] ?? '')), 1000, '')],
                    array_filter((array) ($g['choices'] ?? []), 'is_array'))),
                'correct_answer' => (string) ($g['correct_answer'] ?? ''),
            ],
            'true_false' => ['correct_answer' => is_bool($g['correct_answer'] ?? null) ? $g['correct_answer'] : null],
            'short_answer' => [
                'accepted_answers' => self::strings($g['accepted_answers'] ?? []),
                'normalization' => ['trim' => true, 'case_sensitive' => (bool) ($g['normalization']['case_sensitive'] ?? false), 'remove_diacritics' => false],
            ],
            'composite' => ['mode' => 'per_part', 'parts' => self::cleanParts((array) ($g['parts'] ?? []), $no)],
            default => [],
        };

        return $q;
    }

    private static function cleanParts(array $parts, int $no): array
    {
        $out = [];
        $errors = [];
        foreach (array_values(array_filter($parts, 'is_array')) as $k => $p) {
            $code = substr('abcdefghij', $k, 1) ?: (string) ($k + 1);
            $type = (string) ($p['response_type'] ?? '');
            if (! in_array($type, self::PART_TYPES, true)) {
                continue;
            }
            $part = ['code' => $code, 'prompt' => Str::limit(trim((string) ($p['prompt'] ?? '')), 2000, ''), 'response_type' => $type, 'points' => 1];
            if ($type === 'true_false') {
                if (! is_bool($p['correct_answer'] ?? null)) {
                    $errors[] = "Câu {$no}, ý {$code}): chưa chọn Đúng / Sai.";
                }
                $part['correct_answer'] = (bool) ($p['correct_answer'] ?? false);
            } elseif ($type === 'short_answer') {
                $part['accepted_answers'] = self::strings($p['accepted_answers'] ?? []);
                if ($part['accepted_answers'] === []) {
                    $errors[] = "Câu {$no}, ý {$code}): chưa nhập đáp án.";
                }
            } else {
                $choices = array_values(array_intersect(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], self::strings($p['choices'] ?? [])));
                $part['choices'] = count($choices) >= 2 ? $choices : ['A', 'B', 'C', 'D'];
                $part['correct_answer'] = in_array($p['correct_answer'] ?? null, $part['choices'], true) ? $p['correct_answer'] : $part['choices'][0];
            }
            $out[] = $part;
        }
        if ($errors !== []) {
            self::fail($errors);
        }

        return $out;
    }

    private static function strings(mixed $list): array
    {
        return array_values(array_filter(array_map(fn ($v) => Str::limit(trim((string) $v), 500, ''), array_filter((array) $list, 'is_scalar')), fn ($v) => $v !== ''));
    }

    private static function fail(array $errors): never
    {
        throw ValidationException::withMessages(['general_form' => array_values(array_unique($errors))]);
    }
}

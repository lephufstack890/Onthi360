<?php

namespace App\Services\Teacher;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\Visibility;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Repositories\Contracts\QuestionBankRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Services\PdfTextExtractor;
use App\Services\QuestionPublishGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Tổng hợp dữ liệu cho teacher.questions.index/create/edit — kho riêng của
 * giáo viên (6.5): chỉ giáo viên tạo/chỉnh/sử dụng trong lớp của mình; không
 * mặc định thấy/sửa kho chung hoặc kho giáo viên khác.
 */
class QuestionService
{
    /** Giới hạn hiển thị/trang — đủ lớn để "Tất cả" thực sự hiện hết trong đa số trường hợp thực tế. */
    private const LIST_LIMIT = 200;

    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionBankRepositoryInterface $questionBanks,
        private readonly QuestionPublishGuard $publishGuard,
        private readonly TagRepositoryInterface $tags,
        // SỬA 3/9 (nối trích PDF thành text cho đề bài nhập ZIP) — xem
        // placeholderBodyForZipImport() bên dưới.
        private readonly PdfTextExtractor $pdfTextExtractor,
    ) {}

    /** teacher.questions.create/.edit — danh sách tag để tick chọn (xem App\Models\Tag). */
    public function allTags(): Collection
    {
        return $this->tags->allOrderedByName();
    }

    /**
     * teacher.questions.index — kho riêng của giáo viên + tab "Kho chung" chỉ để XEM
     * (6.5: giáo viên vẫn không tự sửa/phát hành/lưu trữ được câu thuộc Kho chung —
     * đó là việc của Admin/Editor; teacher.assessments.store vẫn chỉ nhận câu thuộc
     * đúng kho riêng của giáo viên đó khi soạn đề, không đổi ở đây).
     */
    public function listForTeacher(User $user, string $tab): array
    {
        $counts = [
            'all' => $this->questions->countByOwner($user->id),
            'published' => $this->questions->countByOwner($user->id, ContentStatus::Published->value),
            'draft' => $this->questions->countByOwner($user->id, ContentStatus::Draft->value),
            'shared' => $this->questions->countShared(),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('teacher.questions.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Đã phát hành', 'href' => route('teacher.questions.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => $counts['published']],
            ['label' => 'Nháp', 'href' => route('teacher.questions.index', ['tab' => 'draft']), 'active' => $tab === 'draft', 'count' => $counts['draft']],
            ['label' => 'Kho chung (chỉ xem)', 'href' => route('teacher.questions.index', ['tab' => 'shared']), 'active' => $tab === 'shared', 'count' => $counts['shared']],
        ];

        if ($tab === 'shared') {
            $questions = $this->questions->sharedLatestWithOwner(self::LIST_LIMIT)
                ->map(fn (Question $q) => $this->mapQuestionRow($q, readOnly: true))
                ->all();

            return ['tab' => $tab, 'tabs' => $tabs, 'questions' => $questions, 'total' => $counts['shared']];
        }

        $statusFilter = match ($tab) {
            'published' => ContentStatus::Published->value,
            'draft' => ContentStatus::Draft->value,
            default => null,
        };

        $total = match ($tab) {
            'published' => $counts['published'],
            'draft' => $counts['draft'],
            default => $counts['all'],
        };

        $questions = $this->questions->byOwner($user->id, $statusFilter, self::LIST_LIMIT)
            ->map(fn (Question $q) => $this->mapQuestionRow($q, readOnly: false))
            ->all();

        return ['tab' => $tab, 'tabs' => $tabs, 'questions' => $questions, 'total' => $total];
    }

    private function mapQuestionRow(Question $q, bool $readOnly): array
    {
        return [
            'id' => $q->id,
            'title' => $q->title,
            'type' => $q->type->value,
            'status' => $q->status === ContentStatus::Published ? 'Phát hành' : ($q->status === ContentStatus::Archived ? 'Lưu trữ' : $this->draftLabel($q)),
            'tone' => $q->status === ContentStatus::Published ? 'success' : ($q->status === ContentStatus::Archived ? 'neutral' : 'warning'),
            'canPublish' => ! $readOnly && $q->status === ContentStatus::Draft,
            'canArchive' => ! $readOnly && $q->status !== ContentStatus::Archived,
            'readOnly' => $readOnly,
        ];
    }

    /**
     * Nhãn nháp chi tiết dựa trên App\Services\QuestionPublishGuard::canPublish() —
     * cùng một cổng kiểm tra dùng khi thật sự phát hành (6.2/6.4), tránh 2 nơi lệch luật.
     */
    private function draftLabel(Question $q): string
    {
        $decision = $this->publishGuard->canPublish($q);

        if ($decision->allowed) {
            return 'Nháp';
        }

        return match ($decision->primaryReasonCode) {
            'missing_content' => 'Nháp — thiếu nội dung',
            'requires_new_version' => 'Nháp — cần tạo phiên bản mới',
            default => 'Nháp — thiếu cấu hình chấm',
        };
    }

    /** Chỉ giáo viên sở hữu mới được xem/sửa (6.5: không mặc định thấy/sửa kho giáo viên khác). */
    public function findOwned(User $teacher, int $id): Question
    {
        $question = $this->questions->findOrFail($id);

        abort_unless($question->owner_type === OwnerType::Teacher && (int) $question->owner_id === $teacher->id, 403);

        return $question;
    }

    public function findOrCreatePersonalBank(User $teacher): QuestionBank
    {
        $bank = $this->questionBanks->findPersonalBank($teacher->id);

        if ($bank !== null) {
            return $bank;
        }

        return $this->questionBanks->create([
            'name' => 'Kho câu hỏi của '.$teacher->name,
            'owner_type' => OwnerType::Teacher,
            'owner_id' => $teacher->id,
        ]);
    }

    /** teacher.questions.store — tạo câu hỏi mới trong kho riêng (6.5), luôn bắt đầu "Nháp". */
    public function store(User $teacher, array $data): Question
    {
        $bank = $this->findOrCreatePersonalBank($teacher);

        $question = $this->questions->create([
            'bank_id' => $bank->id,
            'code' => 'Q-'.$teacher->id.'-'.now()->format('ymd').'-'.random_int(1000, 9999),
            'owner_type' => OwnerType::Teacher,
            'owner_id' => $teacher->id,
            'visibility' => Visibility::Private,
            'status' => ContentStatus::Draft,
            'version' => 1,
            'created_by' => $teacher->id,
            ...$this->buildAttributes($data),
        ]);

        $question->tags()->sync($this->resolveTagIds($data));

        return $question;
    }

    /**
     * teacher.questions.update — nếu câu đã có người làm (AttemptAnswer tồn tại), KHÔNG sửa
     * âm thầm mà tạo phiên bản mới (6.2: "không sửa âm thầm làm thay đổi ý nghĩa kết quả cũ"),
     * dùng lại đúng App\Services\QuestionPublishGuard::createNewVersion() — cùng luật với
     * lúc publish, tránh 2 nơi lệch nhau.
     */
    public function update(Question $question, array $data): Question
    {
        $attributes = $this->buildAttributes($data);

        if ($this->publishGuard->hasBeenAttempted($question)) {
            $newVersion = $this->publishGuard->createNewVersion($question, $attributes);
            $newVersion->tags()->sync($this->resolveTagIds($data));

            return $newVersion;
        }

        $question->update($attributes);
        $question->tags()->sync($this->resolveTagIds($data));

        return $question;
    }

    /**
     * teacher.questions.publish — cùng cổng App\Services\QuestionPublishGuard dùng cho mọi
     * nguồn nhập liệu (6.2, 6.4), không tách riêng luật cho câu tạo tay và câu từ OCR.
     *
     * @throws \Illuminate\Validation\ValidationException nếu chưa đủ điều kiện phát hành.
     */
    public function publish(Question $question): Question
    {
        $decision = $this->publishGuard->canPublish($question);

        if (! $decision->allowed) {
            throw \Illuminate\Validation\ValidationException::withMessages(['publish' => $decision->message]);
        }

        $question->update(['status' => ContentStatus::Published]);

        return $question;
    }

    /** teacher.questions.archive — gỡ khỏi lưu hành, không xóa (Table 27: chỉ 4 trạng thái nội dung). */
    public function archive(Question $question): Question
    {
        $question->update(['status' => ContentStatus::Archived]);

        return $question;
    }

    private function buildAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'points' => (int) $data['points'],
            'grading_config' => $this->buildGradingConfig($data['type'], $data),
        ];
    }

    /**
     * Cấu trúc grading_config theo loại câu (6.1/6.2) — đọc lại bởi
     * Question::hasMinimumGradingConfig() để xác định đủ điều kiện phát hành.
     *
     * SỬA 19/8 — LỖI CHẤM ĐIỂM THẬT (phát hiện khi làm Giai đoạn 6): 'correct_options' PHẢI
     * lưu CHỈ SỐ (int 0-3, khớp thứ tự mảng 'options') vì App\Services\AttemptService::
     * gradeMcq() so khớp bằng array_map('intval', ...) — trước đây hàm này lưu THẲNG giá trị
     * $data['correct_option'] gửi từ form (khi đó là chữ cái "A"/"B"/"C"/"D", xem sửa cùng lúc
     * ở resources/views/teacher/questions/create.blade.php), intval("B")/("C")/("D") đều ra 0
     * → mọi câu Trắc nghiệm giáo viên tự tạo tay có đáp án đúng KHÁC phương án A đều bị chấm
     * SAI cho học sinh chọn đúng. Ép (int) ở đây, đúng y hệt Admin\ContentService::
     * buildGradingConfig() đã làm từ đầu (nơi \DUY NHẤT trước đó làm đúng logic này).
     */
    private function buildGradingConfig(string $type, array $data): array
    {
        return match ($type) {
            'mcq' => [
                'options' => array_values(array_filter($data['options'] ?? [], fn ($o) => filled($o))),
                'correct_options' => isset($data['correct_option']) && $data['correct_option'] !== ''
                    ? [(int) $data['correct_option']]
                    : [],
            ],
            'fill_blank' => [
                'accepted_answers' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['accepted_answers'] ?? ''))), fn ($v) => $v !== '')),
                'case_sensitive' => (bool) ($data['case_sensitive'] ?? false),
            ],
            'coding' => array_filter([
                'test_cases' => $data['test_cases_parsed'] ?? [],
                'time_limit_ms' => filled($data['time_limit_ms'] ?? null) ? (int) $data['time_limit_ms'] : null,
                'memory_limit_mb' => filled($data['memory_limit_mb'] ?? null) ? (int) $data['memory_limit_mb'] : null,
                // SỬA 24/8 — xem App\Services\Admin\ContentService::buildGradingConfig() cùng
                // ngày: 3 khoá dưới đây chỉ được "Nhập từ gói ZIP" điền (form nhập tay không có
                // trường tương ứng) — giữ lại trong grading_config để dành cho khi có judge
                // chấm code thật sau này. Vô hại với luồng tạo/sửa câu hỏi thủ công hiện tại.
                'languages' => $data['languages'] ?? null,
                'file_io' => $data['file_io'] ?? null,
                'subtasks' => $data['subtasks'] ?? null,
            ], fn ($v) => $v !== null),
            default => [],
        };
    }

    /**
     * SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): gộp tag có sẵn (tick,
     * "tag_ids[]") + tag gõ mới (cách nhau bằng dấu phẩy, "new_tags") thành 1 danh sách ID
     * để sync vào Question::tags(). Cố ý TRÙNG LOGIC với App\Services\Admin\ContentService::
     * resolveTagIds() thay vì gọi chéo sang service của Admin — 2 tầng Teacher/Admin trong
     * codebase này vốn độc lập nhau (không service nào gọi service của tầng còn lại), tách
     * riêng 1 helper dùng chung cho cả 2 tầng sẽ phải tạo 1 lớp mới chỉ để tránh 10 dòng
     * trùng — chưa đáng, nếu sau này logic phức tạp hơn thì tách ra App\Services\TagResolver
     * dùng chung.
     */
    private function resolveTagIds(array $data): array
    {
        $tagIds = array_map('intval', $data['tag_ids'] ?? []);
        $newNames = array_filter(array_map('trim', explode(',', (string) ($data['new_tags'] ?? ''))), fn ($n) => $n !== '');

        foreach ($newNames as $name) {
            $tagIds[] = $this->tags->findOrCreateByName($name)->id;
        }

        return array_values(array_unique($tagIds));
    }

    // ================= Câu hỏi lập trình — "Nhập từ gói ZIP" (24/8) =================
    // Bản mirror phía giáo viên của App\Services\Admin\ContentService::questionStoreFromZipPackage()
    // (cùng logic đọc gói ZIP "OT360-QPACK", tái sử dụng store() nguyên vẹn) — lặp lại thay vì
    // gọi chéo sang service của Admin, đúng quy ước độc lập Teacher/Admin đã có sẵn trong file
    // này (xem ghi chú ở resolveTagIds() trên).

    private const MAX_ZIP_PACKAGE_KB = 20480; // 20MB — gói ZIP gồm cả PDF đề+lời giải+nhiều test case

    public static function maxZipPackageKb(): int
    {
        return self::MAX_ZIP_PACKAGE_KB;
    }

    /**
     * teacher.questions.zipImport — tái sử dụng store() nguyên vẹn bên trên (code câu hỏi vẫn
     * tự sinh 'Q-{teacher}-{ymd}-{random}' như mọi câu giáo viên tạo tay, KHÔNG lấy từ tên tệp
     * ZIP — khác Admin vì Admin cần mã gõ tay nên mới lấy từ tên tệp). LƯU Ý ĐÃ BÁO CHO KHÁCH
     * (giống bản Admin): test case nhiều dòng nhập từ ZIP an toàn nhờ 'test_cases_parsed', nhưng
     * nếu sau đó sửa lại qua ô "Test cases" thủ công (dạng text "input => output" mỗi dòng) thì
     * nội dung nhiều dòng có thể bị hiểu sai — hạn chế có sẵn từ trước, không phải lỗi mới.
     *
     * @throws ValidationException nếu gói ZIP không mở được, thiếu/sai question.json, hoặc
     *                              không có test case hợp lệ nào trong thư mục tests/.
     */
    public function storeFromZipPackage(User $teacher, UploadedFile $zip): Question
    {
        $package = $this->parseZipQuestionPackage($zip);
        $json = $package['json'];
        $content = $json['content'] ?? [];
        $grading = $json['grading'] ?? [];

        $points = isset($content['points']) ? (int) round((float) $content['points']) : 0;

        $tagNames = array_values(array_filter(array_map('trim', array_merge(
            $json['taxonomy']['tags'] ?? [],
            $json['taxonomy']['keywords'] ?? [],
        )), fn ($t) => $t !== ''));

        $data = [
            'type' => 'coding',
            'title' => $content['title'] ?? 'Câu hỏi lập trình (nhập từ ZIP)',
            'body' => $this->placeholderBodyForZipImport($content, $package['attachments']),
            'points' => max(0, $points),
            'test_cases_parsed' => $package['testCases'],
            'time_limit_ms' => $grading['time_limit_ms'] ?? 1000,
            'memory_limit_mb' => $grading['memory_limit_mb'] ?? 256,
            'languages' => $grading['languages'] ?? null,
            'file_io' => $grading['file_io'] ?? null,
            'subtasks' => $json['subtasks'] ?? null,
            'new_tags' => implode(',', $tagNames),
        ];

        $question = $this->store($teacher, $data);

        $question->update([
            'metadata' => [
                'source_package' => [
                    'schema' => $json['schema'] ?? null,
                    'original_filename' => $zip->getClientOriginalName(),
                    'imported_at' => now()->toIso8601String(),
                ],
                'taxonomy' => $json['taxonomy'] ?? null,
                'pedagogy' => $json['pedagogy'] ?? null,
                'attribution' => $json['attribution'] ?? null,
                'attachments' => $this->storeZipAttachments($question, $package['attachments']),
            ],
        ]);

        return $question;
    }

    /**
     * teacher.questions.attachment — chỉ trả tệp của câu hỏi CHÍNH giáo viên này sở hữu
     * (findOwned() chặn 403 nếu không), giống mọi hành động khác trên câu hỏi riêng ở service này.
     */
    public function attachmentInfo(User $teacher, int $questionId, string $kind): array
    {
        $question = $this->findOwned($teacher, $questionId);
        $attachments = $question->metadata['attachments'] ?? [];
        if (! isset($attachments[$kind]['path'])) {
            abort(404);
        }

        return [
            'path' => $attachments[$kind]['path'],
            'filename' => $attachments[$kind]['filename'] ?? basename($attachments[$kind]['path']),
        ];
    }

    /**
     * Mở gói ZIP, đọc + kiểm tra question.json (schema "OT360-QPACK", content.type =
     * "programming"), gom test case từ tests/<số>/ (1 tệp tên chứa "input", 1 tệp tên chứa
     * "output" — không cố định đúng tên "INPUT.INP"/"OUTPUT.OUT"), và đọc nội dung 3 tệp đính
     * kèm cố định (statement.pdf/solution.pdf/reference/official.cpp) nếu có. Xem bản gốc ở
     * App\Services\Admin\ContentService::parseZipQuestionPackage() — cùng logic.
     *
     * @return array{json: array, testCases: array<int, array{input:string, output:string}>, attachments: array<string, array{content:string, filename:string}>}
     */
    private function parseZipQuestionPackage(UploadedFile $zip): array
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
        if (! str_starts_with($schema, 'OT360-QPACK') || $contentType !== 'programming') {
            $zipArchive->close();
            throw ValidationException::withMessages([
                'zip_package' => 'Gói ZIP không đúng định dạng OT360-QPACK cho câu hỏi lập trình (schema/loại nội dung không khớp).',
            ]);
        }

        $attachmentNames = ['statement.pdf' => 'statement', 'solution.pdf' => 'solution', 'reference/official.cpp' => 'reference'];
        $attachments = [];
        $testFolders = [];

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

        if ($testCases === []) {
            throw ValidationException::withMessages([
                'zip_package' => 'Không tìm thấy test case hợp lệ trong gói ZIP (cần thư mục tests/<số>/ chứa 2 tệp input/output).',
            ]);
        }

        return ['json' => $json, 'testCases' => $testCases, 'attachments' => $attachments];
    }

    /**
     * SỬA 3/9 (khách chốt: "hiển thị thẳng đề bài dạng text, khỏi hiển thị file") — thử trích
     * chữ thật từ statement.pdf đính kèm (nếu gói ZIP có) qua PdfTextExtractor, dùng THẲNG làm
     * body — chỉ rơi về dòng ghi chú cũ khi không có statement.pdf hoặc trích lỗi/rỗng (PDF là
     * ảnh scan) — cùng lý do/cách làm với Admin\ContentService::placeholderBodyForZipImport(),
     * xem docblock đầy đủ ở đó.
     *
     * @param  array<string, array{content:string, filename:string}>  $rawAttachments  $package['attachments'] TRƯỚC khi lưu disk.
     */
    private function placeholderBodyForZipImport(array $content, array $rawAttachments = []): string
    {
        $statementPdf = $rawAttachments['statement']['content'] ?? null;
        if ($statementPdf !== null) {
            $extracted = $this->pdfTextExtractor->extractText($statementPdf);
            if ($extracted !== null) {
                return $this->pdfTextExtractor->toBodyHtml($extracted);
            }
        }

        $note = 'Đề bài đầy đủ nằm trong tệp PDF đính kèm (nhập từ gói ZIP) — xem mục "Tệp đính kèm" ở trang Sửa câu hỏi.';
        $title = $content['title'] ?? null;

        return $title ? "{$title}\n\n{$note}" : $note;
    }

    /**
     * Lưu tệp đính kèm (đề/lời giải/code mẫu) vào đúng disk 'local' theo đường dẫn khoá bởi
     * $question->id — chỉ gọi SAU KHI câu hỏi đã tạo (cần id để đặt đường dẫn).
     *
     * @param  array<string, array{content:string, filename:string}>  $attachments
     * @return array<string, array{path:string, filename:string}>
     */
    private function storeZipAttachments(Question $question, array $attachments): array
    {
        $stored = [];
        foreach ($attachments as $kind => $attachment) {
            $extension = pathinfo($attachment['filename'], PATHINFO_EXTENSION) ?: 'bin';
            $path = "questions/{$question->id}/{$kind}.{$extension}";
            Storage::disk('local')->put($path, $attachment['content']);
            $stored[$kind] = ['path' => $path, 'filename' => $attachment['filename']];
        }

        return $stored;
    }
}

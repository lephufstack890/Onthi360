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
use App\Support\QuestionZipPackage;
use App\Support\SubjectCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            // SỬA 8/9 (4) — 'type' giờ là NHÃN tiếng Việt để hiện thẳng ra bảng; 'typeValue' giữ
            // mã gốc cho phần chọn icon ở view (icon map theo mã, không theo nhãn — đổi chữ nhãn
            // sau này không làm mất icon). Xem App\Enums\QuestionType::label().
            'type' => $q->type->label(),
            'typeValue' => $q->type->value,
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
        $attributes = $this->buildAttributes($data, $question);

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

    /**
     * @param  Question|null  $current  câu đang sửa (null = đang tạo mới) — cần để GỘP metadata,
     *                                  xem mergeDifficultyIntoMetadata().
     */
    private function buildAttributes(array $data, ?Question $current = null): array
    {
        $attributes = [
            'type' => $data['type'],
            'title' => $data['title'],
            // SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — câu giáo viên tự tạo cũng hiện ở
            // tab "Câu hỏi" của admin, nên cũng cần Môn/Khối, nếu không sẽ đọng lại nhóm "Chưa
            // phân loại". Chuẩn hoá qua SubjectCatalog, giá trị lạ -> null (giống Admin\ContentService).
            'subject' => SubjectCatalog::normalize($data['subject'] ?? null),
            'grade' => SubjectCatalog::normalizeGrade($data['grade'] ?? null),
            'body' => $data['body'],
            'points' => (int) $data['points'],
            // SỬA 18/9 (khách: "tạo câu hỏi chỗ giáo viên cũng không thấy Độ khó") — độ khó do
            // giáo viên chọn, lưu vào metadata.difficulty giống Admin\ContentService.
            'metadata' => $this->mergeDifficultyIntoMetadata($current?->metadata, $data),
        ];

        // SỬA 18/9 — câu Composite (nhiều phần/nhiều dạng con, CHỈ tạo qua nhập ZIP) KHÔNG có
        // form nhập tay tương ứng, buildGradingConfig() sẽ rơi vào nhánh `default => []` và XOÁ
        // SẠCH cấu hình các phần con nếu gọi cho nó. Giữ NGUYÊN grading_config đã nhập từ ZIP —
        // đúng y cách App\Services\Admin\ContentService::questionUpdate() đã xử lý từ 31/8.
        if (($data['type'] ?? null) !== 'composite') {
            $attributes['grading_config'] = $this->buildGradingConfig($data['type'], $data);
        }

        return $attributes;
    }

    /**
     * SỬA 18/9 — bản sao của App\Services\Admin\ContentService::mergeDifficultyIntoMetadata()
     * (2 service tách riêng theo vai trò, không dùng chung trait). Phải GỘP chứ không ghi đè cả
     * cột metadata vì câu nhập từ gói ZIP còn giữ 'attachments'/'source_package'/'taxonomy' ở đó
     * (xem storeFromZipPackage() + attachmentInfo()) — ghi đè sẽ làm mất tệp đính kèm.
     * Bỏ trống ô Độ khó => XOÁ key => trang Luyện tập public tự suy lại theo điểm như trước.
     *
     * @return array<string, mixed>
     */
    private function mergeDifficultyIntoMetadata(?array $current, array $data): array
    {
        $metadata = $current ?? [];
        $value = $data['difficulty'] ?? null;

        if (is_string($value) && in_array($value, ['easy', 'medium', 'hard', 'expert'], true)) {
            $metadata['difficulty'] = $value;
        } else {
            unset($metadata['difficulty']);
        }

        return $metadata;
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

    // ================= "Nhập từ gói ZIP" (24/8, mở rộng mọi loại câu 18/9) =================
    // Bản mirror phía giáo viên của App\Services\Admin\ContentService::questionStoreFromZipPackage().
    // SỬA 18/9 (khách: "tạo câu hỏi chỗ giáo viên cũng cho nhập file zip như admin luôn nha") —
    // phần ĐỌC gói ZIP + dựng grading_config trước đây là bản CHÉP TAY chỉ hiểu content.type =
    // 'programming', nên giáo viên không nhập được gói trắc nghiệm/điền khuyết dù admin nhập
    // được. Giờ cả 2 service gọi CHUNG App\Support\QuestionZipPackage (1 nguồn sự thật, không
    // còn 2 bản lệch nhau); phần LƯU vẫn tách riêng vì giáo viên lưu vào kho riêng của mình
    // (owner_type=teacher, mã câu tự sinh) còn admin lưu vào Kho chung.

    private const MAX_ZIP_PACKAGE_KB = 20480; // 20MB — gói ZIP gồm cả PDF đề+lời giải+nhiều test case

    public static function maxZipPackageKb(): int
    {
        return self::MAX_ZIP_PACKAGE_KB;
    }

    /**
     * teacher.questions.zipImport — nhập 1 câu hỏi từ gói ZIP OT360-QPACK vào kho riêng của
     * giáo viên. LƯU Ý ĐÃ BÁO CHO KHÁCH (giống bản Admin): test case nhiều dòng nhập từ ZIP an
     * toàn vì lưu thẳng mảng, nhưng nếu sau đó sửa lại qua ô "Test cases" thủ công (dạng text
     * "input => output" mỗi dòng) thì nội dung nhiều dòng có thể bị hiểu sai — hạn chế có sẵn
     * từ trước, không phải lỗi mới.
     *
     * @throws ValidationException nếu gói ZIP không mở được, thiếu/sai question.json, loại nội
     *                              dung chưa hỗ trợ, hoặc gói lập trình không có test case hợp lệ.
     */
    public function storeFromZipPackage(User $teacher, UploadedFile $zip): Question
    {
        $package = QuestionZipPackage::parse($zip);
        $json = $package['json'];
        $content = $json['content'] ?? [];
        $contentType = (string) ($content['type'] ?? '');

        $points = isset($content['points']) ? (int) round((float) $content['points']) : 0;

        $tagNames = array_values(array_filter(array_map('trim', array_merge(
            $json['taxonomy']['tags'] ?? [],
            $json['taxonomy']['keywords'] ?? [],
        )), fn ($t) => $t !== ''));

        // SỬA 8/9 (3) — lấy Môn/Khối từ taxonomy của gói ZIP, cùng cách Admin\ContentService
        // ::questionStoreFromZipPackage() đang làm.
        $classification = SubjectCatalog::fromTaxonomy($json['taxonomy'] ?? []);

        // SỬA 18/9 (khách: "cho nhập file zip như admin luôn") — KHÔNG còn gán cứng 'coding':
        // loại câu + grading_config giờ suy từ content.type của gói (QuestionZipPackage), nên
        // giáo viên nhập được cả gói trắc nghiệm/đúng-sai/điền khuyết/nhiều phần y như admin.
        // Vì grading_config dựng THẲNG theo cấu trúc JSON (khác hẳn cấu trúc form nhập tay),
        // hàm này tạo Question trực tiếp thay vì gọi lại store() — giống hệt cách bản Admin làm.
        $type = QuestionZipPackage::questionType($contentType);
        $bank = $this->findOrCreatePersonalBank($teacher);

        $question = $this->questions->create([
            'bank_id' => $bank->id,
            // Mã câu hỏi vẫn tự sinh 'Q-{teacher}-{ymd}-{random}' như mọi câu giáo viên tạo tay,
            // KHÔNG lấy từ tên tệp ZIP — khác Admin vì Admin cần mã gõ tay nên mới lấy từ tên tệp.
            'code' => 'Q-'.$teacher->id.'-'.now()->format('ymd').'-'.random_int(1000, 9999),
            'type' => $type,
            'title' => $content['title'] ?? 'Câu hỏi (nhập từ ZIP)',
            'subject' => $classification['subject'],
            'grade' => $classification['grade'],
            'body' => $this->placeholderBodyForZipImport($content, $package['attachments']),
            'points' => max(0, $points),
            'grading_config' => QuestionZipPackage::gradingConfig($contentType, $json, $package['testCases']),
            'owner_type' => OwnerType::Teacher,
            'owner_id' => $teacher->id,
            'visibility' => Visibility::Private,
            'status' => ContentStatus::Draft,
            'version' => 1,
            'created_by' => $teacher->id,
        ]);

        $question->update([
            'metadata' => [
                'source_package' => [
                    'schema' => $json['schema'] ?? null,
                    'content_type' => $contentType,
                    'original_filename' => $zip->getClientOriginalName(),
                    'imported_at' => now()->toIso8601String(),
                ],
                'taxonomy' => $json['taxonomy'] ?? null,
                'pedagogy' => $json['pedagogy'] ?? null,
                'attribution' => $json['attribution'] ?? null,
                'attachments' => $this->storeZipAttachments($question, $package['attachments']),
                // SỬA 18/9 — audio/ảnh... đính kèm khai trong question.json['assets'] (trước đây
                // bản giáo viên bỏ qua hẳn, nên gói có audio nhập vào là mất tiếng) — xem
                // storeZipAssets() + Question::findAsset().
                'assets' => $this->storeZipAssets($question, $package['assets']),
                // SỬA 18/9 — độ khó gói ZIP khai sẵn ở pedagogy.difficulty, đổ vào
                // metadata.difficulty để trang Luyện tập public hiện/lọc đúng.
                'difficulty' => QuestionZipPackage::difficultyFrom($json),
            ],
        ]);

        if ($tagNames !== []) {
            $question->tags()->sync(array_map(fn ($name) => $this->tags->findOrCreateByName($name)->id, $tagNames));
        }

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

    /**
     * SỬA 18/9 — lưu asset (audio/ảnh...) khai trong question.json['assets'], SONG SONG với
     * storeZipAttachments() ở trên nhưng khác ở chỗ SỐ LƯỢNG/loại không cố định trước (3 tên
     * statement/solution/reference) — lưu theo asset id thay vì theo 'kind' cố định. Bản sao
     * đúng y App\Services\Admin\ContentService::storeZipAssets() (đường dẫn giống hệt nên
     * Question::findAsset() dùng lại được nguyên vẹn cho câu của giáo viên).
     *
     * @param  array<int, array{id:string, kind:string, filename:string, content:string, transcript:?string, alt_text:?string}>  $assets
     * @return array<int, array{id:string, kind:string, path:string, filename:string, transcript:?string, alt_text:?string}>
     */
    private function storeZipAssets(Question $question, array $assets): array
    {
        $stored = [];
        foreach ($assets as $asset) {
            $extension = pathinfo($asset['filename'], PATHINFO_EXTENSION) ?: 'bin';
            $path = "questions/{$question->id}/assets/{$asset['id']}.{$extension}";
            Storage::disk('local')->put($path, $asset['content']);

            $stored[] = [
                'id' => $asset['id'],
                'kind' => $asset['kind'],
                'path' => $path,
                'filename' => $asset['filename'],
                'transcript' => $asset['transcript'],
                'alt_text' => $asset['alt_text'],
            ];
        }

        return $stored;
    }
}

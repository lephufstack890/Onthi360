<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\QuestionType;
use App\Enums\UploadedDocumentStatus;
use App\Enums\Visibility;
use App\Models\DraftQuestion;
use App\Models\QuestionBank;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;
use App\Support\DocumentTextExtractor;
use App\Support\ExamTextSplitter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Luồng "Nhập đề" phía Admin/Editor (ADM-03, 6.4, 6.5): tải Word/PDF/OCR ->
 * trích xuất -> phân rã bản nháp -> rà soát -> chuyển vào "Kho chung" (LUÔN ở
 * dạng Nháp — OCR không tự phát hành, vẫn phải tự bấm "Phát hành" từng câu ở
 * admin.content.questions.publish như quy trình tạo câu hỏi tay bình thường).
 *
 * Cố ý KHÔNG dùng chung App\Services\Teacher\DocumentImportService: pipeline
 * trích xuất/tách câu là logic thuần (App\Support\DocumentTextExtractor,
 * App\Support\ExamTextSplitter) nên tái dùng thẳng, nhưng đích đến của
 * promote() khác hẳn (Kho chung — owner_type=shared, không phải kho riêng
 * của 1 giáo viên) nên tách riêng để không phải chèn nhánh rẽ theo vai trò
 * vào 1 service dùng chung, dễ rối khi đọc lại sau này.
 *
 * Khác với phía Teacher: tài liệu/câu nháp ở đây thuộc "Kho chung" — bất kỳ
 * Admin/Editor nào (cùng nhóm quyền role:admin,super_admin,editor) cũng rà
 * soát/sửa/xóa/gộp/chuyển vào kho được, không giới hạn theo đúng người đã tải
 * lên (khác Teacher — nơi tài liệu là sở hữu riêng của từng giáo viên).
 */
class DocumentImportService
{
    private const MAX_FILE_KB = 20480; // 20MB

    public function __construct(
        private readonly UploadedDocumentRepositoryInterface $documents,
        private readonly DraftQuestionRepositoryInterface $drafts,
        private readonly QuestionRepositoryInterface $questions,
        private readonly DocumentTextExtractor $extractor,
        private readonly ExamTextSplitter $splitter,
    ) {}

    public static function maxFileKb(): int
    {
        return self::MAX_FILE_KB;
    }

    /**
     * Kho câu hỏi chung dùng chung toàn hệ thống — CÙNG một bản ghi với
     * App\Services\Admin\ContentService::sharedBank() (tra theo đúng
     * owner_type+name nên firstOrCreate() luôn trả về đúng 1 hàng).
     */
    private function sharedBank(): QuestionBank
    {
        return QuestionBank::firstOrCreate(
            ['owner_type' => OwnerType::Shared->value, 'name' => 'Kho chung'],
        );
    }

    /** admin.content.questions.import.store — tải tệp lên và xử lý ngay trong request (6.4). */
    public function import(User $admin, UploadedFile $file): UploadedDocument
    {
        $path = $file->store('content-imports/'.$admin->id, 'local');

        $document = $this->documents->create([
            'uploader_id' => $admin->id,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => UploadedDocumentStatus::Uploaded,
            'virus_scan_status' => 'pending',
        ]);

        $this->process($document);

        return $document->refresh();
    }

    /**
     * Xử lý đồng bộ ngay trong request (không qua queue) — giống hệt lý do ở
     * App\Services\Teacher\DocumentImportService::process(): không phụ thuộc
     * việc có worker nền đang chạy hay không.
     */
    public function process(UploadedDocument $document): void
    {
        $absolutePath = Storage::disk('local')->path($document->storage_path);

        $document->update(['status' => UploadedDocumentStatus::Scanning]);

        if (! $this->signatureMatchesExtension($absolutePath, $document->original_filename)) {
            $document->update([
                'status' => UploadedDocumentStatus::Failed,
                'virus_scan_status' => 'flagged',
                'error_log' => 'Nội dung tệp không khớp định dạng khai báo (.docx/.pdf) — từ chối xử lý.',
            ]);

            return;
        }

        // Chưa có ClamAV trên máy để quét virus thật (16 mục 6) — điểm nối vào đây
        // sau này: gọi `clamscan` và chặn nếu nhiễm, trước khi đặt 'clean'.
        $document->update(['virus_scan_status' => 'clean', 'status' => UploadedDocumentStatus::Processing]);

        try {
            $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
            $result = match ($extension) {
                'docx' => $this->extractor->extractDocx($absolutePath),
                'pdf' => $this->extractor->extractPdf($absolutePath),
                default => throw new \RuntimeException('Định dạng tệp không được hỗ trợ (chỉ nhận .docx, .pdf).'),
            };
        } catch (\Throwable $e) {
            $document->update([
                'status' => UploadedDocumentStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);

            return;
        }

        $text = trim($result['text'] ?? '');

        if ($text === '') {
            $document->update([
                'status' => UploadedDocumentStatus::Failed,
                'error_log' => 'Không trích xuất được nội dung văn bản nào từ tệp.',
            ]);

            return;
        }

        if ($result['usedOcr'] ?? false) {
            $document->ocrResult()->updateOrCreate([], [
                'pages' => $result['pages'] ?? null,
            ]);
        }

        $chunks = $this->splitter->split($text);

        if ($chunks === []) {
            $document->update([
                'status' => UploadedDocumentStatus::Failed,
                'error_log' => 'Trích xuất được văn bản nhưng không tách được câu nào.',
            ]);

            return;
        }

        foreach ($chunks as $order => $chunk) {
            $this->drafts->create([
                'uploaded_document_id' => $document->id,
                'order' => $order,
                'type_guess' => $chunk['type_guess'],
                'raw_text' => $chunk['raw_text'],
                'structured_draft' => $chunk['structured'],
                'confidence' => $chunk['confidence'],
                'review_status' => 'pending',
            ]);
        }

        $document->update(['status' => UploadedDocumentStatus::NeedsReview]);
    }

    /** Không giới hạn theo người tải lên — tài liệu thuộc "Kho chung", cả nhóm Admin/Editor cùng rà soát được. */
    public function findDocument(int $id): UploadedDocument
    {
        $document = $this->documents->query()->find($id);

        abort_if($document === null, 404);

        return $document;
    }

    public function findDraft(int $id): DraftQuestion
    {
        $draft = $this->drafts->query()->with('uploadedDocument')->find($id);

        abort_if($draft === null, 404);

        return $draft;
    }

    public function addManualDraft(UploadedDocument $document): DraftQuestion
    {
        $nextOrder = ((int) $this->drafts->query()->where('uploaded_document_id', $document->id)->max('order')) + 1;

        return $this->drafts->create([
            'uploaded_document_id' => $document->id,
            'order' => $nextOrder,
            'type_guess' => null,
            'raw_text' => '',
            'structured_draft' => ['title' => '', 'body' => '', 'points' => 1],
            'confidence' => 'unknown',
            'review_status' => 'pending',
        ]);
    }

    public function updateDraft(DraftQuestion $draft, string $type, array $data): DraftQuestion
    {
        $draft->update([
            'type_guess' => $type,
            'structured_draft' => $this->buildStructured($type, $data),
            'review_status' => 'reviewed',
        ]);

        return $draft;
    }

    /** @throws ValidationException nếu 2 câu không cùng 1 tệp hoặc gộp với chính nó. */
    public function mergeDrafts(DraftQuestion $draft, int $mergeWithId): void
    {
        $other = $this->findDraft($mergeWithId);

        if ($other->id === $draft->id) {
            throw ValidationException::withMessages(['merge_with_id' => 'Không thể gộp câu với chính nó.']);
        }

        if ($other->uploaded_document_id !== $draft->uploaded_document_id) {
            throw ValidationException::withMessages(['merge_with_id' => 'Chỉ gộp được các câu trong cùng 1 tệp đã tải lên.']);
        }

        $combinedRaw = trim($draft->raw_text."\n".$other->raw_text);
        $s1 = $draft->structured_draft ?? [];
        $s2 = $other->structured_draft ?? [];
        $mergedBody = trim(($s1['body'] ?? '')."\n".($s2['body'] ?? ''));

        $draft->update([
            'raw_text' => $combinedRaw,
            'type_guess' => null,
            'structured_draft' => array_merge($s1, ['body' => $mergedBody]),
            'confidence' => 'low',
            'review_status' => 'pending',
        ]);

        $other->update(['review_status' => 'discarded']);
    }

    public function discardDraft(DraftQuestion $draft): void
    {
        $draft->update(['review_status' => 'discarded']);
    }

    /**
     * admin.content.documents.promote — chuyển các câu đã rà soát vào "Kho
     * chung", luôn tạo ở trạng thái Nháp (6.4: "OCR không tự phát hành").
     * Chặn toàn bộ nếu còn câu chưa đủ điều kiện, liệt kê rõ câu nào.
     *
     * @throws ValidationException
     */
    public function promote(User $admin, UploadedDocument $document): int
    {
        $activeDrafts = $document->draftQuestions()
            ->where('review_status', '!=', 'discarded')
            ->whereNull('promoted_question_id')
            ->get();

        if ($activeDrafts->isEmpty()) {
            throw ValidationException::withMessages(['promote' => 'Không còn câu nào để chuyển vào kho — mọi câu đã được chuyển hoặc đã bị xóa.']);
        }

        $notReady = [];
        foreach ($activeDrafts as $draft) {
            [$ready, $reason] = $this->draftReadiness($draft);
            if (! $ready) {
                $notReady[] = 'Câu '.($draft->order + 1).': '.$reason;
            }
        }

        if ($notReady !== []) {
            throw ValidationException::withMessages([
                'promote' => 'Chưa thể chuyển vào kho — '.implode('; ', $notReady).'.',
            ]);
        }

        $bank = $this->sharedBank();
        $count = 0;

        DB::transaction(function () use ($activeDrafts, $admin, $bank, &$count) {
            foreach ($activeDrafts as $draft) {
                $structured = $draft->structured_draft ?? [];
                $type = $draft->type_guess instanceof QuestionType ? $draft->type_guess->value : $draft->type_guess;

                $question = $this->questions->create([
                    'bank_id' => $bank->id,
                    'code' => $this->generateUniqueCode(),
                    'type' => QuestionType::from($type),
                    'title' => $structured['title'] ?? '',
                    'body' => $structured['body'] ?? '',
                    'points' => (int) ($structured['points'] ?? 1),
                    'grading_config' => $this->gradingConfigFromStructured($type, $structured),
                    'owner_type' => OwnerType::Shared,
                    'owner_id' => null,
                    'visibility' => Visibility::Public,
                    'status' => ContentStatus::Draft,
                    'version' => 1,
                    'created_by' => $admin->id,
                ]);

                $draft->update(['review_status' => 'merged', 'promoted_question_id' => $question->id]);
                $count++;
            }
        });

        $document->update(['status' => UploadedDocumentStatus::Promoted]);

        return $count;
    }

    /** Tránh trùng cột `code` (unique) — App\Services\Admin\ContentService::questionStore() chặn tay khi admin tự gõ mã. */
    private function generateUniqueCode(): string
    {
        do {
            $code = 'Q-ADM-'.now()->format('ymd').'-'.random_int(1000, 9999);
        } while ($this->questions->query()->where('code', $code)->exists());

        return $code;
    }

    /** @return array{0: bool, 1: ?string} */
    private function draftReadiness(DraftQuestion $draft): array
    {
        $s = $draft->structured_draft ?? [];

        if (blank($s['title'] ?? null) || blank($s['body'] ?? null)) {
            return [false, 'thiếu tiêu đề hoặc nội dung'];
        }

        $type = $draft->type_guess instanceof QuestionType ? $draft->type_guess->value : $draft->type_guess;

        if ($type === null) {
            return [false, 'chưa chọn dạng câu hỏi'];
        }

        return match ($type) {
            'mcq' => (filled($s['correct_option'] ?? null) && (int) ($s['points'] ?? 0) > 0)
                ? [true, null] : [false, 'thiếu đáp án đúng hoặc điểm'],
            'fill_blank' => (filled($s['accepted_answers'] ?? null) && (int) ($s['points'] ?? 0) > 0)
                ? [true, null] : [false, 'thiếu đáp án chấp nhận hoặc điểm'],
            'coding' => (filled($s['test_cases'] ?? null) && filled($s['time_limit_ms'] ?? null) && filled($s['memory_limit_mb'] ?? null))
                ? [true, null] : [false, 'thiếu test, giới hạn thời gian hoặc bộ nhớ'],
            default => [false, 'dạng câu hỏi không hợp lệ'],
        };
    }

    private function buildStructured(string $type, array $data): array
    {
        $base = [
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'points' => (int) ($data['points'] ?? 1),
        ];

        return match ($type) {
            'mcq' => $base + [
                'options' => [
                    $data['options'][0] ?? '',
                    $data['options'][1] ?? '',
                    $data['options'][2] ?? '',
                    $data['options'][3] ?? '',
                ],
                'correct_option' => $data['correct_option'] ?? null,
            ],
            'fill_blank' => $base + [
                'accepted_answers' => $data['accepted_answers'] ?? '',
                'case_sensitive' => (bool) ($data['case_sensitive'] ?? false),
            ],
            'coding' => $base + [
                'test_cases' => $data['test_cases'] ?? '',
                'time_limit_ms' => filled($data['time_limit_ms'] ?? null) ? (int) $data['time_limit_ms'] : null,
                'memory_limit_mb' => filled($data['memory_limit_mb'] ?? null) ? (int) $data['memory_limit_mb'] : null,
            ],
            default => $base,
        };
    }

    /** Giống hệt App\Services\Teacher\DocumentImportService::gradingConfigFromStructured() — giữ đồng bộ nếu sửa. */
    private function gradingConfigFromStructured(string $type, array $s): array
    {
        return match ($type) {
            'mcq' => [
                'options' => array_values(array_filter($s['options'] ?? [], fn ($o) => filled($o))),
                // App\Services\AttemptService::gradeMcq() so khớp bằng (int) -> phải lưu
                // CHỈ SỐ 0-3, không phải chữ cái A-D (form rà soát vẫn dùng chữ cái A-D).
                'correct_options' => array_values(array_filter(
                    [$this->letterToIndex($s['correct_option'] ?? null)],
                    fn ($v) => $v !== null
                )),
            ],
            'fill_blank' => [
                'accepted_answers' => array_values(array_filter(
                    array_map('trim', explode(',', (string) ($s['accepted_answers'] ?? ''))),
                    fn ($v) => $v !== ''
                )),
                'case_sensitive' => (bool) ($s['case_sensitive'] ?? false),
            ],
            'coding' => [
                'test_cases' => $this->parseTestCases((string) ($s['test_cases'] ?? '')),
                'time_limit_ms' => $s['time_limit_ms'] ?? null,
                'memory_limit_mb' => $s['memory_limit_mb'] ?? null,
            ],
            default => [],
        };
    }

    /** 'A'/'B'/'C'/'D' (không phân biệt hoa/thường) -> 0/1/2/3; giá trị khác -> null (bỏ qua). */
    private function letterToIndex(?string $letter): ?int
    {
        return match (strtoupper((string) $letter)) {
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 3,
            default => null,
        };
    }

    /** Giống hệt App\Services\Teacher\DocumentImportService::parseTestCases() — giữ đồng bộ nếu sửa. */
    private function parseTestCases(string $raw): array
    {
        $cases = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=>')) {
                continue;
            }
            [$input, $output] = explode('=>', $line, 2);
            $cases[] = ['input' => trim($input), 'output' => trim($output)];
        }

        return $cases;
    }

    private function signatureMatchesExtension(string $absolutePath, string $originalFilename): bool
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 8);
        fclose($handle);

        return match ($extension) {
            'pdf' => str_starts_with((string) $header, '%PDF-'),
            'docx' => str_starts_with((string) $header, "PK\x03\x04"),
            default => false,
        };
    }
}

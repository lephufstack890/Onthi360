<?php

namespace App\Services;

use App\Enums\AssessmentContentMode;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\PublishAnswerRule;
use App\Models\Assessment;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * SỬA 19/8 (Giai đoạn 3 — "Bộ đề", nhập hàng loạt nhiều đề PDF cùng lúc): dùng chung cho CẢ
 * Admin (owner_type=shared) LẪN Giáo viên (owner_type=teacher) — cùng lý do tách riêng với
 * App\Services\PdfAssessmentEditingService (đó là sửa 1 đề đã có sẵn; đây là TẠO HÀNG LOẠT đề
 * mới). Không tự kiểm tra quyền — service gọi vào (Admin\ContentService/Teacher\
 * AssessmentService) tự chịu trách nhiệm xác nhận người gọi được phép trước khi gọi.
 *
 * 2 cách nhập, theo đúng lựa chọn của khách (19/8):
 *  1. splitIntoAssessments() — tải 1 file PDF LỚN gộp nhiều đề nối tiếp nhau, khai mã đề +
 *     khoảng trang cho từng đề, hệ thống TỰ CẮT file gốc thành từng file PDF riêng (thư viện
 *     setasign/fpdi — thuần PHP, không cần binary ngoài như pdftk/poppler trên server) rồi tự
 *     tạo từng Assessment. KHÔNG đọc/OCR nội dung — chỉ cắt đúng số trang được khai, đáp án
 *     vẫn phải nhập tay sau đó ở màn "Quản lý đề PDF" (16/8 mục 1.2, giữ nguyên từ Giai đoạn 1).
 *  2. createFromMultipleFiles() — tải nhiều file PDF RIÊNG LẺ cùng lúc, mỗi file đã LÀ 1 đề
 *     hoàn chỉnh sẵn — không cần cắt trang, chỉ tạo hàng loạt Assessment nhanh hơn thay vì tạo
 *     từng cái một qua màn "Tạo đề PDF mới" hiện có.
 *
 * LƯU Ý TRIỂN KHAI: cần chạy `composer require setasign/fpdi setasign/fpdf` trên server thật
 * trước khi tính năng này hoạt động — 2 gói đó CHƯA có sẵn trong composer.json của dự án tính
 * tới Giai đoạn 2 (xem README_PHASE3_DEPLOY.md kèm theo bundle này).
 */
class PdfBulkImportService
{
    private const PDF_DISK = 'local';

    // File gộp nhiều đề nối tiếp nhau thường nặng hơn nhiều so với 1 đề đơn (50MB, xem
    // PdfAssessmentEditingService::MAX_PDF_KB) — vd gộp 10 đề x 5-10 trang scan.
    private const MAX_SOURCE_PDF_KB = 204800; // 200MB

    public function __construct(
        private readonly AssessmentRepositoryInterface $assessments,
    ) {}

    public static function maxSourcePdfKb(): int
    {
        return self::MAX_SOURCE_PDF_KB;
    }

    /**
     * @param  array<int, array{exam_code:?string, title:string, type:string, from_page:int, to_page:int}>  $rows
     * @return Collection<int, Assessment>
     *
     * @throws ValidationException nếu 1 khoảng trang không hợp lệ, hoặc file gốc không mở
     *                              được (hỏng/có mật khẩu — bản FPDI miễn phí không đọc được
     *                              PDF đã khoá mật khẩu).
     */
    public function splitIntoAssessments(
        User $creator,
        OwnerType $ownerType,
        ?int $ownerId,
        UploadedFile $sourcePdf,
        array $rows,
    ): Collection {
        if ($rows === []) {
            throw ValidationException::withMessages(['rows' => 'Cần khai ít nhất 1 đề (mã đề + khoảng trang) để tách.']);
        }

        // SỬA 19/8 (fix lỗi 500 khi trùng mã đề — anh Phú báo lỗi UniqueConstraintViolation
        // khi test bulk/multi): assessments.exam_code có ràng buộc UNIQUE ở DB, nhưng bulk
        // trước đây không kiểm tra trước — vừa trùng mã đề đã có sẵn, vừa trùng NGAY giữa các
        // dòng trong cùng 1 đợt tải lên, đều rơi thẳng vào UniqueConstraintViolationException
        // (500 kỹ thuật) thay vì thông báo nghiệp vụ rõ ràng. Kiểm tra TRƯỚC khi tạo bất kỳ đề
        // nào — fail nhanh, không tạo dở dang.
        $this->assertExamCodesAvailable(array_column($rows, 'exam_code'));

        // Lưu tạm file gốc để FPDI đọc bằng đường dẫn thật trên đĩa (setSourceFile() cần path,
        // không nhận UploadedFile trực tiếp) — xoá ngay sau khi tách xong, không cần giữ lại.
        $sourcePath = $sourcePdf->store('assessments/bulk-source', self::PDF_DISK);
        $absoluteSourcePath = Storage::disk(self::PDF_DISK)->path($sourcePath);

        try {
            $probe = new Fpdi();
            $totalPages = $probe->setSourceFile($absoluteSourcePath);
        } catch (Throwable $e) {
            $this->deleteStoredFile($sourcePath);

            throw ValidationException::withMessages([
                'source_pdf' => 'Không đọc được file PDF gốc — có thể file hỏng hoặc đang đặt mật khẩu (cần gỡ mật khẩu trước khi tải lên).',
            ]);
        }

        foreach ($rows as $idx => $row) {
            $from = (int) $row['from_page'];
            $to = (int) $row['to_page'];
            if ($from < 1 || $to < $from || $to > $totalPages) {
                $this->deleteStoredFile($sourcePath);

                throw ValidationException::withMessages([
                    "rows.{$idx}.to_page" => "Đề #".($idx + 1)." khai trang {$from}-{$to} không hợp lệ — file gốc chỉ có {$totalPages} trang.",
                ]);
            }
        }

        $created = collect();

        // SỬA 19/8: gộp cả đợt vào 1 transaction — nếu 1 dòng lỗi giữa chừng (vd lỗi DB khác
        // không lường trước), MỌI đề đã tạo trong đợt này bị rollback hết thay vì để lại vài
        // đề dở dang (đã tạo Assessment nhưng chưa kịp gắn pdf_path) — trước đây admin gặp lỗi
        // 500 giữa đợt vẫn còn sót lại các đề tạo trước đó trong DB dù trang báo lỗi.
        try {
            DB::transaction(function () use ($rows, $creator, $ownerType, $ownerId, $absoluteSourcePath, $created) {
                foreach ($rows as $row) {
                    $assessment = $this->createDraftAssessment($creator, $ownerType, $ownerId, [
                        'title' => $row['title'],
                        'type' => $row['type'],
                        'exam_code' => $row['exam_code'] ?: null,
                    ]);

                    $splitFpdi = new Fpdi();
                    $splitFpdi->setSourceFile($absoluteSourcePath);
                    for ($page = (int) $row['from_page']; $page <= (int) $row['to_page']; $page++) {
                        $templateId = $splitFpdi->importPage($page);
                        $size = $splitFpdi->getTemplateSize($templateId);
                        $splitFpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $splitFpdi->useTemplate($templateId);
                    }

                    $splitDir = "assessments/{$assessment->id}";
                    Storage::disk(self::PDF_DISK)->makeDirectory($splitDir);
                    $splitRelativePath = "{$splitDir}/".Str::random(32).'.pdf';
                    $splitFpdi->Output(Storage::disk(self::PDF_DISK)->path($splitRelativePath), 'F');

                    $this->assessments->update($assessment, [
                        'pdf_path' => $splitRelativePath,
                        'pdf_original_name' => ($row['exam_code'] ?: $row['title']).'.pdf',
                    ]);

                    $created->push($assessment->fresh());
                }
            });
        } finally {
            $this->deleteStoredFile($sourcePath);
        }

        return $created;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, array{exam_code:?string, title:string, type:string}>  $meta  cùng
     *         thứ tự/index với $files (khớp theo vị trí học sinh chọn file ở form, xem
     *         resources/views/.../bulk.blade.php).
     * @return Collection<int, Assessment>
     */
    public function createFromMultipleFiles(User $creator, OwnerType $ownerType, ?int $ownerId, array $files, array $meta): Collection
    {
        // SỬA 19/8 (fix lỗi 500 khi trùng mã đề) — cùng lý do với splitIntoAssessments() ở trên.
        $this->assertExamCodesAvailable(array_column($meta, 'exam_code'));

        $created = collect();

        DB::transaction(function () use ($files, $meta, $creator, $ownerType, $ownerId, $created) {
            foreach ($files as $idx => $file) {
                $row = $meta[$idx] ?? null;
                if ($row === null) {
                    continue; // không tin index client gửi lệch — bỏ qua file không có metadata khớp
                }

                $assessment = $this->createDraftAssessment($creator, $ownerType, $ownerId, [
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'exam_code' => $row['exam_code'] ?: null,
                ]);

                $path = $file->store("assessments/{$assessment->id}", self::PDF_DISK);

                $this->assessments->update($assessment, [
                    'pdf_path' => $path,
                    'pdf_original_name' => $file->getClientOriginalName(),
                ]);

                $created->push($assessment->fresh());
            }
        });

        return $created;
    }

    /**
     * SỬA 19/8: assessments.exam_code có ràng buộc UNIQUE ở DB (migration
     * add_pdf_fields_to_assessments_table) — kiểm tra TRƯỚC khi tạo, chặn cả 2 kiểu trùng:
     * (1) trùng với đề đã có sẵn trong hệ thống, (2) trùng NGAY giữa các dòng trong cùng 1 đợt
     * tải lên (chưa kịp có trong DB nên rule `unique` của Laravel không tự bắt được kiểu này).
     * Bỏ qua mã đề để trống (exam_code nullable — nhiều dòng cùng để trống vẫn hợp lệ).
     *
     * @param  array<int, ?string>  $examCodes
     *
     * @throws ValidationException
     */
    private function assertExamCodesAvailable(array $examCodes): void
    {
        $filled = array_values(array_filter($examCodes, fn ($code) => filled($code)));
        if ($filled === []) {
            return;
        }

        $counts = array_count_values($filled);
        $duplicatesInBatch = array_keys(array_filter($counts, fn ($count) => $count > 1));
        if ($duplicatesInBatch !== []) {
            throw ValidationException::withMessages([
                'exam_code' => 'Mã đề bị trùng ngay trong đợt tải lên này: '.implode(', ', $duplicatesInBatch).'.',
            ]);
        }

        $alreadyTaken = Assessment::whereIn('exam_code', $filled)->pluck('exam_code');
        if ($alreadyTaken->isNotEmpty()) {
            throw ValidationException::withMessages([
                'exam_code' => 'Mã đề đã tồn tại trong hệ thống, không thể trùng: '.$alreadyTaken->implode(', ').'.',
            ]);
        }
    }

    /** @param  array{title:string, type:string, exam_code:?string}  $data */
    private function createDraftAssessment(User $creator, OwnerType $ownerType, ?int $ownerId, array $data): Assessment
    {
        return $this->assessments->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'content_mode' => AssessmentContentMode::PdfAnswerSheet->value,
            'exam_code' => $data['exam_code'],
            'total_points' => 0,
            'duration_minutes' => null,
            'publish_answer_rule' => PublishAnswerRule::AfterDeadline->value,
            'status' => ContentStatus::Draft->value,
            'version' => 1,
            'owner_type' => $ownerType->value,
            'owner_id' => $ownerId,
            'created_by' => $creator->id,
        ]);
    }

    private function deleteStoredFile(?string $path): void
    {
        if (filled($path) && Storage::disk(self::PDF_DISK)->exists($path)) {
            Storage::disk(self::PDF_DISK)->delete($path);
        }
    }
}

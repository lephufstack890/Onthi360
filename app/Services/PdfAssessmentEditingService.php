<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentCodingItem;
use App\Models\AssessmentCodingTestCase;
use App\Enums\AnswerSheetQuestionType;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 18/8 (đề PDF + phiếu đáp án, 16/8 mục 1.2/5/6): logic soạn 1 Assessment
 * content_mode=pdf_answer_sheet — tách ra đây để dùng chung cho CẢ Admin
 * (App\Services\Admin\ContentService, đề owner_type=shared, admin toàn quyền) LẪN Giáo
 * viên (App\Services\Teacher\AssessmentService, đề owner_type=teacher, mặc định riêng tư —
 * "Đề giáo viên tạo mặc định riêng tư. Nếu muốn đưa đề ra kho chung thì Admin duyệt.").
 * KHÔNG tự kiểm tra quyền sở hữu ở đây — mỗi service gọi vào (Admin/Teacher) tự chịu trách
 * nhiệm xác nhận người gọi được phép soạn đúng $assessment này TRƯỚC khi gọi (Teacher dùng
 * lại AssessmentService::findOwned(), Admin không cần vì admin luôn có toàn quyền nội dung).
 */
class PdfAssessmentEditingService
{
    // Cùng quy ước disk riêng tư với App\Services\Admin\DocumentImportService — KHÔNG public,
    // chỉ tải được qua route có middleware auth/role kiểm tra (Giai đoạn 4 sẽ gắn thêm kiểm
    // tra AccessRight thật khi mở PDF cho học sinh).
    private const PDF_DISK = 'local';

    private const MAX_PDF_KB = 51200; // 50MB — đề scan nhiều trang thường nặng hơn tệp nhập câu hỏi (20MB)

    public function __construct(
        private readonly PdfAssessmentPublishGuard $publishGuard,
        private readonly AssessmentRepositoryInterface $assessments,
    ) {}

    public static function maxPdfKb(): int
    {
        return self::MAX_PDF_KB;
    }

    /** Dữ liệu cho màn "Quản lý đề PDF" — dùng chung cả 2 nơi gọi vào. */
    public function formData(Assessment $assessment): array
    {
        $assessment->load(['answerKeys', 'codingItems.testCases']);

        return [
            'assessment' => $assessment,
            'answerKeys' => $assessment->answerKeys,
            'codingItems' => $assessment->codingItems,
            'answerSheetTypes' => [
                AnswerSheetQuestionType::SingleChoice->value => AnswerSheetQuestionType::SingleChoice->label(),
                AnswerSheetQuestionType::TrueFalseGroup->value => AnswerSheetQuestionType::TrueFalseGroup->label(),
                AnswerSheetQuestionType::ShortAnswer->value => AnswerSheetQuestionType::ShortAnswer->label(),
            ],
            'publishDecision' => $this->publishGuard->canPublish($assessment),
        ];
    }

    /**
     * Lưu file PDF/lời giải (nếu có tải mới), mã đề, phạm vi xem thử, và THAY TOÀN BỘ đáp án
     * đúng từng câu (xoá hết rồi tạo lại theo đúng form — khách chốt 16/8 mục 1.2: "đáp án
     * nhập trực tiếp trên form", không có nhập bằng Excel/CSV).
     *
     * @param  array<int, array{question_no:int, question_type:string, correct_answer:mixed, points:int}>  $answerKeyRows
     */
    public function update(
        Assessment $assessment,
        array $data,
        array $answerKeyRows,
        ?UploadedFile $pdf,
        ?UploadedFile $solutionPdf,
    ): Assessment {
        $update = [
            'exam_code' => $data['exam_code'] ?: null,
            'preview_page_from' => $data['preview_page_from'] ?: null,
            'preview_page_to' => $data['preview_page_to'] ?: null,
        ];

        if ($pdf !== null) {
            $this->deleteStoredFile($assessment->pdf_path);
            $update['pdf_path'] = $pdf->store('assessments/'.$assessment->id, self::PDF_DISK);
            $update['pdf_original_name'] = $pdf->getClientOriginalName();
        }

        if ($solutionPdf !== null) {
            $this->deleteStoredFile($assessment->solution_pdf_path);
            $update['solution_pdf_path'] = $solutionPdf->store('assessments/'.$assessment->id, self::PDF_DISK);
        }

        $assessment->answerKeys()->delete();
        $answerPoints = 0;
        foreach ($answerKeyRows as $row) {
            $points = (int) ($row['points'] ?? 0);
            $assessment->answerKeys()->create([
                'question_no' => (int) $row['question_no'],
                'question_type' => $row['question_type'],
                'correct_answer' => $row['correct_answer'],
                'points' => $points,
            ]);
            $answerPoints += $points;
        }

        $codingPoints = (int) $assessment->codingItems()->sum('points');
        $update['total_points'] = $answerPoints + $codingPoints;

        return $this->assessments->update($assessment, $update);
    }

    private function deleteStoredFile(?string $path): void
    {
        if (filled($path) && Storage::disk(self::PDF_DISK)->exists($path)) {
            Storage::disk(self::PDF_DISK)->delete($path);
        }
    }

    public function codingItemStore(Assessment $assessment, array $data): AssessmentCodingItem
    {
        $item = $assessment->codingItems()->create([
            'code' => $data['code'],
            'title' => $data['title'],
            'pdf_page' => $data['pdf_page'] ?: null,
            'allowed_languages' => $data['allowed_languages'] ?? ['cpp', 'python'],
            'time_limit_ms' => $data['time_limit_ms'] ?? 1000,
            'memory_limit_kb' => $data['memory_limit_kb'] ?? 262144,
            'points' => $data['points'] ?? 0,
        ]);

        $this->recomputeCodingTotalPoints($assessment);

        return $item;
    }

    public function codingItemUpdate(AssessmentCodingItem $item, array $data): AssessmentCodingItem
    {
        $item->update([
            'code' => $data['code'],
            'title' => $data['title'],
            'pdf_page' => $data['pdf_page'] ?: null,
            'allowed_languages' => $data['allowed_languages'] ?? ['cpp', 'python'],
            'time_limit_ms' => $data['time_limit_ms'] ?? 1000,
            'memory_limit_kb' => $data['memory_limit_kb'] ?? 262144,
            'points' => $data['points'] ?? 0,
        ]);

        $this->recomputeCodingTotalPoints($item->assessment);

        return $item;
    }

    public function codingItemDestroy(AssessmentCodingItem $item): void
    {
        $assessment = $item->assessment;
        foreach ($item->testCases as $testCase) {
            $this->deleteStoredFile($testCase->input_path);
            $this->deleteStoredFile($testCase->expected_output_path);
        }
        $item->delete();

        $this->recomputeCodingTotalPoints($assessment);
    }

    private function recomputeCodingTotalPoints(Assessment $assessment): void
    {
        $answerPoints = (int) $assessment->answerKeys()->sum('points');
        $codingPoints = (int) $assessment->codingItems()->sum('points');
        $this->assessments->update($assessment, ['total_points' => $answerPoints + $codingPoints]);
    }

    /**
     * Tải 1 gói ZIP chứa nhiều cặp file input/output (16/8 mục 1.2: "Test case/tệp kèm theo
     * có thể tải bằng gói ZIP; đây không phải nhập đáp án bằng Excel/CSV"). Quy ước đặt tên
     * trong ZIP: mỗi cặp cùng tên gốc, khác đuôi input (.in/.inp/.txt) và output (.out/.ans/
     * .expected) — ghép cặp theo tên gốc giống nhau. File không ghép được cặp thì bỏ qua,
     * không chặn cả gói.
     */
    public function codingItemImportTestCasesZip(AssessmentCodingItem $item, UploadedFile $zip): int
    {
        $zipArchive = new ZipArchive();
        if ($zipArchive->open($zip->getRealPath()) !== true) {
            throw ValidationException::withMessages(['test_cases_zip' => 'Không mở được gói ZIP, kiểm tra lại tệp.']);
        }

        $inputExtensions = ['in', 'inp', 'txt'];
        $outputExtensions = ['out', 'ans', 'expected'];
        $entriesByBaseName = [];

        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $name = $zipArchive->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue; // thư mục con trong zip, bỏ qua
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $base = pathinfo($name, PATHINFO_FILENAME);

            if (in_array($ext, $inputExtensions, true)) {
                $entriesByBaseName[$base]['input'] = $name;
            } elseif (in_array($ext, $outputExtensions, true)) {
                $entriesByBaseName[$base]['output'] = $name;
            }
        }

        $order = (int) $item->testCases()->max('order');
        $created = 0;

        foreach ($entriesByBaseName as $base => $pair) {
            if (! isset($pair['input'], $pair['output'])) {
                continue; // thiếu 1 trong 2 vế của cặp — không đoán bừa, bỏ qua cặp này
            }

            $order++;
            $storedDir = "assessments/coding-items/{$item->id}/test-case-{$order}";

            $inputContent = $zipArchive->getFromName($pair['input']);
            $outputContent = $zipArchive->getFromName($pair['output']);
            if ($inputContent === false || $outputContent === false) {
                continue;
            }

            Storage::disk(self::PDF_DISK)->put("{$storedDir}/input.txt", $inputContent);
            Storage::disk(self::PDF_DISK)->put("{$storedDir}/output.txt", $outputContent);

            AssessmentCodingTestCase::create([
                'coding_item_id' => $item->id,
                'order' => $order,
                'input_path' => "{$storedDir}/input.txt",
                'expected_output_path' => "{$storedDir}/output.txt",
                'is_sample' => $created === 0, // cặp đầu tiên trong gói mặc định làm test mẫu
            ]);
            $created++;
        }

        $zipArchive->close();

        if ($created === 0) {
            throw ValidationException::withMessages([
                'test_cases_zip' => 'Không tìm thấy cặp input/output hợp lệ trong gói ZIP (đặt tên input/output cùng tên gốc, khác đuôi .in/.out).',
            ]);
        }

        return $created;
    }
}

<?php

namespace App\Services;

use App\Enums\AssessmentContentMode;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Admin\ContentService;
use App\Services\Teacher\AssessmentService as TeacherAssessmentService;
use App\Support\ExamZipPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 16/10 (khách: "thêm phần nhập file ZIP chỗ tạo đề / bộ bài trong Kho bài tập / câu hỏi và đề, admin và giáo viên") —
 * tạo HẲN 1 đề PDF (content_mode = pdf_answer_sheet) từ gói OT360-EPACK 2.0 (đọc bằng App\Support\ExamZipPackage):
 *   1. thông tin đề + PDF xem trước / hướng dẫn / bài mẫu + ảnh bìa  -> đi lại đúng hàm tạo đề sẵn có của từng vai trò
 *      (admin: ContentService::assessmentStore — đề Kho chung; giáo viên: paperStore + paperDetailUpdate — đề riêng tư);
 *   2. tệp đề + lời giải + phiếu đáp án (mỗi câu tự mang dạng của nó)   -> PdfAssessmentEditingService::update();
 *   3. từng bài lập trình + bộ test                                      -> codingItemStore() + codingItemAddTestCases().
 * Tất cả trong 1 transaction: gói lỗi giữa chừng thì không để lại đề dở dang. Đề tạo ra ở trạng thái Nháp như mọi đề mới,
 * người dùng được đưa tới màn "Quản lý đề PDF" để rà lại trước khi phát hành.
 */
class ExamZipImportService
{
    public function __construct(
        private readonly PdfAssessmentEditingService $pdfEditing,
        private readonly ContentService $content,
        private readonly TeacherAssessmentService $teacherAssessments,
    ) {}

    /** @return array{assessment: Assessment, summary: string} */
    public function importForAdmin(User $admin, UploadedFile $zip): array
    {
        $p = ExamZipPackage::parse($zip);

        try {
            $this->assertExamCodeFree($p['exam']['exam_code']);
            $type = $this->resolveType($p['exam']['type']);

            $assessment = DB::transaction(function () use ($admin, $p, $type) {
                $files = $p['files'];
                // array_replace (không dùng "+"): "type" đã có sẵn trong $p['exam'] (giá trị thô trong exam.json) — phải GHI ĐÈ
                // bằng loại đã kiểm tra hợp lệ ở trên.
                $assessment = $this->content->assessmentStore($admin, array_replace($p['exam'], [
                    'type' => $type,
                    'preview_pdf' => $files['preview'] ?? null,
                    'guide_pdf' => $files['guide'] ?? null,
                    'sample_pdf' => $files['sample'] ?? null,
                ]), $files['cover'] ?? null);

                // Gói đề luôn là đề PDF + phiếu đáp án, kể cả khi loại là "Luyện tập".
                $assessment->forceFill(['content_mode' => AssessmentContentMode::PdfAnswerSheet->value])->save();

                return $this->fillContent($assessment, $p);
            });
        } finally {
            ExamZipPackage::cleanup($p);
        }

        return ['assessment' => $assessment, 'summary' => ExamZipPackage::summaryText($p['summary'])];
    }

    /** @return array{assessment: Assessment, summary: string} */
    public function importForTeacher(User $teacher, UploadedFile $zip): array
    {
        $p = ExamZipPackage::parse($zip);

        try {
            $this->assertExamCodeFree($p['exam']['exam_code']);
            $type = $this->resolveType($p['exam']['type']);

            $assessment = DB::transaction(function () use ($teacher, $p, $type) {
                $files = $p['files'];
                $assessment = $this->teacherAssessments->paperStore($teacher, [
                    'title' => $p['exam']['title'],
                    'type' => $type,
                    'duration_minutes' => $p['exam']['duration_minutes'],
                    'publish_answer_rule' => $p['exam']['publish_answer_rule'],
                ]);

                $this->teacherAssessments->paperDetailUpdate($assessment, array_replace($p['exam'], [
                    'subtitle' => $p['exam']['subtitle'],
                    'preview_pdf' => $files['preview'] ?? null,
                    'guide_pdf' => $files['guide'] ?? null,
                    'sample_pdf' => $files['sample'] ?? null,
                ]), $files['cover'] ?? null);

                return $this->fillContent($assessment->fresh(), $p);
            });
        } finally {
            ExamZipPackage::cleanup($p);
        }

        return ['assessment' => $assessment, 'summary' => ExamZipPackage::summaryText($p['summary'])];
    }

    /** Bước 2 + 3: tệp đề, lời giải, phiếu đáp án, bài lập trình + test. */
    private function fillContent(Assessment $assessment, array $p): Assessment
    {
        $this->pdfEditing->update(
            $assessment,
            [
                'exam_code' => $p['exam']['exam_code'],
                'preview_page_from' => $p['exam']['preview_page_from'],
                'preview_page_to' => $p['exam']['preview_page_to'],
            ],
            $p['answerKeys'],
            $p['files']['pdf'] ?? null,
            $p['files']['solution'] ?? null,
        );

        foreach ($p['codingItems'] as $c) {
            $item = $this->pdfEditing->codingItemStore($assessment, $c);
            $this->pdfEditing->codingItemAddTestCases($item, $c['tests']);
        }

        return $assessment->fresh();
    }

    /**
     * SỬA 16/10 (khách: "để tạo cho phần LUYỆN TẬP trước") — gói ZIP đề mặc định tạo đề LUYỆN TẬP (type = practice, vẫn là đề
     * PDF + phiếu đáp án): đề loại này tự hiện ở trang Luyện tập công khai + Luyện tập của học sinh khi phát hành
     * (Public\PracticeService lọc type=practice + status=published). Cố ý KHÔNG theo cờ features.assessment_type_practice của
     * form tạo tay bên admin (cờ đó chỉ ẩn lựa chọn ở dropdown). exam.json ghi rõ loại khác hợp lệ thì theo loại đó.
     */
    private function resolveType(?string $raw): string
    {
        // SỬA 16/10 — Bài giao / Đề thi đấu đã ẩn khỏi tạo đề mới: chỉ còn Luyện tập (mặc định) và Đề thi.
        return in_array($raw, ['practice', 'exam'], true) ? $raw : 'practice';
    }

    /** Mã đề là duy nhất trên toàn hệ thống (kể cả đề đã xoá mềm — ràng buộc unique của cột exam_code). */
    private function assertExamCodeFree(?string $code): void
    {
        if ($code === null) {
            return;
        }

        if (Assessment::withTrashed()->where('exam_code', $code)->exists()) {
            throw ValidationException::withMessages([
                'exam_zip' => "Mã đề \"{$code}\" đã có trong hệ thống. Đổi \"exam.code\" trong exam.json (hoặc xoá dòng đó để để trống) rồi tải lại.",
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\Question;
use App\Models\User;
use App\Services\Admin\ContentService;
use App\Services\Teacher\AssessmentService as TeacherAssessmentService;
use App\Services\Teacher\QuestionService as TeacherQuestionService;
use App\Support\GeneralSetZipPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 3/11 (khách: "thêm dạng PHỔ THÔNG — 1 bài nhiều câu trắc nghiệm / điền từ…, nhập bằng gói ZIP đúng mẫu MAU-CAU-HOI;
 * lập trình và mọi logic cũ giữ nguyên") — tạo "Bài phổ thông" từ gói ZIP (đọc bằng App\Support\GeneralSetZipPackage).
 *
 * CHỈ GHÉP các hàm sẵn có, không sửa hàm nào:
 *   · từng câu → đúng hàm "Nhập câu hỏi từ ZIP" của vai trò (admin: ContentService::questionStoreFromZipPackage — Kho chung, Nháp;
 *     giáo viên: Teacher\QuestionService::storeFromZipPackage — kho riêng), rồi ghi thêm nội dung câu ("prompt") và lời giải ngắn
 *     ("explanation") khai trong gói;
 *   · bài → admin: ContentService::assessmentStore (loại Luyện tập, câu hỏi rời) + AssessmentItem như đề Luyện tập nhập ZIP;
 *     giáo viên: Teacher\AssessmentService::store (đề riêng của giáo viên — giao cho lớp như mọi đề khác);
 *   · đánh dấu practice_kind = 'general', đề cả bài (statement.pdf) = PDF xem trước (tab "Đề bài PDF"), lời giải cả bài
 *     (solution.pdf) lưu riêng, công bố đáp án NGAY sau khi nộp (publish_answer_rule = immediately), không giới hạn thời gian.
 * Tất cả trong 1 transaction — gói lỗi giữa chừng không để lại bài dở dang.
 */
class GeneralSetImportService
{
    public const SOLUTION_DIR = 'assessments/general-solutions';

    public function __construct(
        private readonly ContentService $content,
        private readonly TeacherAssessmentService $teacherAssessments,
        private readonly TeacherQuestionService $teacherQuestions,
    ) {}

    /** @return array{assessment: Assessment, summary: string} */
    public function importForAdmin(User $admin, UploadedFile $zip): array
    {
        $this->assertReady();
        $p = GeneralSetZipPackage::parse($zip);
        $p['questions'] = self::spreadTotal($p['questions']);

        try {
            $assessment = DB::transaction(function () use ($admin, $p) {
                $files = $p['files'];
                $assessment = $this->content->assessmentStore($admin, [
                    'title' => $p['meta']['title'],
                    'subtitle' => $p['meta']['subtitle'],
                    'type' => 'practice',
                    'duration_minutes' => null,
                    'publish_answer_rule' => 'immediately',
                    'author' => $p['meta']['author'],
                    'province' => $p['meta']['province'],
                    'academic_year' => $p['meta']['academic_year'],
                    'exam_category' => null,
                    'exam_level' => null,
                    'difficulty_level' => $p['meta']['difficulty_level'],
                    'preview_pdf' => $files['statement'] ?? null,
                ]);

                $total = 0.0;
                foreach (array_values($p['questions']) as $order => $q) {
                    $question = $this->content->questionStoreFromZipPackage($admin, $q['zip']);
                    $this->applyExtras($question, $q);
                    AssessmentItem::create([
                        'assessment_id' => $assessment->id,
                        'question_id' => $question->id,
                        'order' => $order,
                        'points_override' => $q['points'],
                    ]);
                    $total += $q['points'];
                }

                $assessment->forceFill([
                    'total_points' => round($total, 2),
                    'exam_code' => $this->freeCode($p['meta']['code']),
                    'practice_kind' => Assessment::PRACTICE_KIND_GENERAL,
                    'solution_pdf_path' => $this->storeSolution($files['solution'] ?? null),
                ])->save();

                return $assessment->fresh();
            });
        } finally {
            GeneralSetZipPackage::cleanup($p);
        }

        return ['assessment' => $assessment, 'summary' => GeneralSetZipPackage::summaryText($p['summary'])];
    }

    /** @return array{assessment: Assessment, summary: string} */
    public function importForTeacher(User $teacher, UploadedFile $zip): array
    {
        $this->assertReady();
        $p = GeneralSetZipPackage::parse($zip);
        $p['questions'] = self::spreadTotal($p['questions']);

        try {
            $assessment = DB::transaction(function () use ($teacher, $p) {
                $files = $p['files'];
                $ids = [];
                $points = [];
                foreach (array_values($p['questions']) as $q) {
                    $question = $this->teacherQuestions->storeFromZipPackage($teacher, $q['zip']);
                    $this->applyExtras($question, $q);
                    // Câu riêng của chính giáo viên (riêng tư) — phát hành luôn để bài giao được ngay (đề chỉ phát hành khi mọi câu
                    // đã phát hành, xem Teacher\AssessmentService::canPublish()).
                    $question->forceFill(['status' => ContentStatus::Published->value])->save();
                    $ids[] = $question->id;
                    $points[$question->id] = $q['points'];
                }

                $assessment = $this->teacherAssessments->store($teacher, [
                    'title' => $p['meta']['title'],
                    'subtitle' => $p['meta']['subtitle'],
                    'question_ids' => $ids,
                    'points' => $points,
                    'duration_minutes' => null,
                    'publish_answer_rule' => 'immediately',
                    'author' => $p['meta']['author'],
                    'province' => $p['meta']['province'],
                    'academic_year' => $p['meta']['academic_year'],
                    'difficulty_level' => $p['meta']['difficulty_level'],
                    'preview_pdf' => $files['statement'] ?? null,
                ]);

                $assessment->forceFill([
                    'exam_code' => $this->freeCode($p['meta']['code']),
                    'practice_kind' => Assessment::PRACTICE_KIND_GENERAL,
                    'solution_pdf_path' => $this->storeSolution($files['solution'] ?? null),
                ])->save();

                return $assessment->fresh();
            });
        } finally {
            GeneralSetZipPackage::cleanup($p);
        }

        return ['assessment' => $assessment, 'summary' => GeneralSetZipPackage::summaryText($p['summary'])];
    }

    /** SỬA 3/11 (khách: "điểm bài phổ thông chia đều theo tổng 10") — tổng điểm của bài luôn là 10. */
    public const TOTAL_POINTS = 10;

    /**
     * Chia đều TOTAL_POINTS cho các câu (bỏ qua "points" ghi trong gói). Điểm lưu 2 chữ số thập phân nên phần lẻ được dồn
     * 0,01 cho các câu đầu để tổng ĐÚNG 10 — vd 24 câu: 16 câu 0,42 + 8 câu 0,41 = 10; 20 câu: mỗi câu 0,5.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    public static function spreadTotal(array $questions): array
    {
        $n = count($questions);
        if ($n === 0) {
            return $questions;
        }
        $cents = self::TOTAL_POINTS * 100;
        $base = intdiv($cents, $n);
        $extra = $cents - $base * $n;
        foreach (array_values($questions) as $i => $q) {
            $questions[$i]['points'] = round(($base + ($i < $extra ? 1 : 0)) / 100, 2);
        }

        return array_values($questions);
    }

    /** Nội dung câu (prompt) + lời giải ngắn (explanation) khai trong gói — chỉ ghi lên câu VỪA tạo. */
    private function applyExtras(Question $question, array $q): void
    {
        $changes = [];
        // SỬA 3/11 (lỗi "phát hành không được") — điểm GỐC của câu là số nguyên (cột questions.points); gói ghi điểm lẻ (vd 0,25)
        // thì bị làm tròn thành 0 và khâu phát hành chặn ("Thiếu đáp án hoặc điểm"). Điểm thật của câu trong bài là
        // points_override (chia đều tổng 10), nên điểm gốc chỉ cần ≥ 1.
        if ((int) $question->points < 1) {
            $changes['points'] = 1;
        }
        if (! empty($q['prompt'])) {
            $paragraphs = preg_split('/\n{2,}/', trim((string) $q['prompt'])) ?: [];
            $changes['body'] = collect($paragraphs)->map(fn ($x) => trim($x))->filter()->map(fn ($x) => '<p>'.nl2br(e($x)).'</p>')->implode('');
        } elseif (! isset($question->metadata['attachments']['statement'])) {
            // Câu không có nội dung riêng → đọc ở tab "Đề bài PDF" (đề cả bài).
            $changes['body'] = '<p>Câu '.(int) $q['no'].' — xem nội dung câu hỏi ở tab <strong>Đề bài PDF</strong>.</p>';
        }
        if (! empty($q['explanation'])) {
            $changes['metadata'] = array_merge((array) ($question->metadata ?? []), ['explanation' => (string) $q['explanation']]);
        }
        if ($changes !== []) {
            $question->forceFill($changes)->save();
        }
    }

    private function storeSolution(?UploadedFile $file): ?string
    {
        return $file !== null ? $file->store(self::SOLUTION_DIR, 'local') : null;
    }

    /** Mã bài = tên tệp ZIP (như quy tắc gói câu hỏi); trùng mã đã có thì thêm -2, -3… (cột exam_code là duy nhất). */
    private function freeCode(string $code): string
    {
        $candidate = $code;
        for ($n = 2; Assessment::withTrashed()->where('exam_code', $candidate)->exists(); $n++) {
            $candidate = mb_substr($code, 0, 55).'-'.$n;
        }

        return $candidate;
    }

    private function assertReady(): void
    {
        if (! Assessment::supportsPracticeKind()) {
            throw ValidationException::withMessages([
                'general_zip' => 'Máy chủ chưa chạy migration cho tính năng Bài phổ thông — báo quản trị viên chạy "php artisan migrate".',
            ]);
        }
    }
}

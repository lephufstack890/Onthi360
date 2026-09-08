<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Support\SubjectCatalog;
use Illuminate\Console\Command;

/**
 * SỬA 8/9 (3) (khách: "kho câu hỏi giờ làm sao để phân loại được các môn") — 2 cột phân loại
 * mới (questions.subject/grade, xem migration add_subject_grade_to_questions_table) chỉ được
 * điền cho câu tạo/nhập TỪ NAY VỀ SAU. Lệnh này điền ngược cho câu ĐÃ có sẵn trong kho, theo
 * đúng thứ tự ưu tiên:
 *
 *   1. metadata.taxonomy của gói ZIP OT360-QPACK (chính xác — do người đóng gói khai báo).
 *   2. Đoán từ tiền tố mã câu hỏi ("TOAN6UOC_CHUNG_001" -> Toán/lớp 6).
 *
 * Không ra được cả 2 thì ĐỂ TRỐNG — câu đó nằm nhóm "Chưa phân loại" ở bộ lọc tab Câu hỏi để
 * người thật gán tay, cố ý KHÔNG đoán bừa vì gán sai môn tệ hơn là để trống.
 *
 * php artisan questions:backfill-subject --dry-run   — CHỈ xem sẽ gán gì, không ghi gì cả
 * php artisan questions:backfill-subject             — điền cho câu chưa có môn
 * php artisan questions:backfill-subject --force     — gán ĐÈ cả câu đã có môn (dùng khi đã sửa
 *                                                      lại danh mục môn trong SubjectCatalog)
 */
class BackfillQuestionSubject extends Command
{
    protected $signature = 'questions:backfill-subject
        {--dry-run : Chỉ in ra kết quả dự kiến, không ghi vào CSDL}
        {--force : Gán đè cả những câu đã có môn (mặc định chỉ điền câu còn trống)}';

    protected $description = 'Điền Môn học/Khối lớp cho câu hỏi cũ, lấy từ taxonomy gói ZIP hoặc đoán từ tiền tố mã câu hỏi';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Question::query();
        if (! $force) {
            $query->whereNull('subject');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Không có câu hỏi nào cần điền — mọi câu đều đã có môn.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[Chạy thử] ' : '').'Đang xử lý '.$total.' câu hỏi…');

        $fromTaxonomy = 0;
        $fromCode = 0;
        $skipped = 0;
        $bySubject = [];

        // chunkById: kho câu hỏi có thể rất lớn, không nạp hết vào bộ nhớ một lần.
        $query->orderBy('id')->chunkById(200, function ($questions) use (&$fromTaxonomy, &$fromCode, &$skipped, &$bySubject, $dryRun) {
            foreach ($questions as $question) {
                $taxonomy = $question->metadata['taxonomy'] ?? null;
                $result = is_array($taxonomy) ? SubjectCatalog::fromTaxonomy($taxonomy) : ['subject' => null, 'grade' => null];
                $source = 'taxonomy';

                if ($result['subject'] === null) {
                    $result = SubjectCatalog::guessFromCode($question->code);
                    $source = 'code';
                }

                if ($result['subject'] === null) {
                    $skipped++;

                    continue;
                }

                $source === 'taxonomy' ? $fromTaxonomy++ : $fromCode++;
                $label = SubjectCatalog::label($result['subject']) ?? $result['subject'];
                $bySubject[$label] = ($bySubject[$label] ?? 0) + 1;

                if (! $dryRun) {
                    // Cập nhật thẳng, KHÔNG qua Question::save() với event — đây là dọn dữ liệu
                    // phân loại, không phải sửa nội dung câu hỏi (6.2 không áp dụng: không tạo
                    // version mới, không đụng body/grading_config/status).
                    Question::withoutTimestamps(fn () => $question->forceFill([
                        'subject' => $result['subject'],
                        'grade' => $result['grade'] ?? $question->grade,
                    ])->save());
                }
            }
        });

        ksort($bySubject);
        $this->newLine();
        $this->table(
            ['Môn', 'Số câu'],
            collect($bySubject)->map(fn ($count, $label) => [$label, $count])->values()->all(),
        );

        $this->info('Từ taxonomy gói ZIP: '.$fromTaxonomy);
        $this->info('Đoán từ mã câu hỏi : '.$fromCode);
        $this->warn('Không xác định được (giữ "Chưa phân loại"): '.$skipped);

        if ($dryRun) {
            $this->newLine();
            $this->comment('Đây là chạy thử — CHƯA ghi gì vào CSDL. Bỏ --dry-run để ghi thật.');
        }

        return self::SUCCESS;
    }
}

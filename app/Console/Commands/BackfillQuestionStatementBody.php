<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\PdfTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillQuestionStatementBody extends Command
{
    protected $signature = 'questions:backfill-statement-body
        {question? : ID câu hỏi cần chấm lại — bỏ trống nếu dùng --all}
        {--all : Chấm lại TẤT CẢ câu hỏi có đính kèm statement.pdf}
        {--force : Ghi đè cả câu mà body đã bị sửa tay, không còn giữ dòng ghi chú cũ}';

    protected $description = 'Trích lại text từ statement.pdf đã lưu sẵn, thay cho dòng ghi chú placeholder cũ ở Question::body';

    private const PLACEHOLDER_MARKER = 'Đề bài đầy đủ nằm trong tệp PDF đính kèm';

    public function handle(PdfTextExtractor $pdfTextExtractor): int
    {
        $questionId = $this->argument('question');
        $all = (bool) $this->option('all');
        $force = (bool) $this->option('force');

        if (! $questionId && ! $all) {
            $this->error('Cần truyền ID câu hỏi, hoặc dùng --all để chấm lại toàn bộ.');

            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;

        $query = Question::query();
        if ($questionId) {
            $query->whereKey($questionId);
        }

        $query->chunkById(50, function ($questions) use ($pdfTextExtractor, $force, &$updated, &$skipped) {
            foreach ($questions as $question) {
                $path = $question->metadata['attachments']['statement']['path'] ?? null;

                if ($path === null || ! Storage::disk('local')->exists($path)) {
                    continue; // câu này không có statement.pdf — không phải lỗi, chỉ là không áp dụng
                }

                if (! $force && ! str_contains($question->body, self::PLACEHOLDER_MARKER)) {
                    $this->warn("Câu #{$question->id} ({$question->title}): body đã khác dòng ghi chú gốc (có thể đã sửa tay) — BỎ QUA, dùng --force nếu vẫn muốn ghi đè.");
                    $skipped++;

                    continue;
                }

                $extracted = $pdfTextExtractor->extractText(Storage::disk('local')->get($path));

                if ($extracted === null) {
                    $this->warn("Câu #{$question->id} ({$question->title}): trích PDF thất bại/rỗng (có thể là PDF ảnh scan) — GIỮ NGUYÊN body cũ.");
                    $skipped++;

                    continue;
                }

                $question->body = $pdfTextExtractor->toBodyHtml($extracted);
                $question->save();
                $updated++;
                $this->info("Câu #{$question->id} ({$question->title}): đã cập nhật body từ statement.pdf.");
            }
        });

        $this->line("Xong — đã cập nhật {$updated} câu, bỏ qua {$skipped} câu.");

        return self::SUCCESS;
    }
}

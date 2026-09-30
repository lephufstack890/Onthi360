<?php

namespace App\Console\Commands;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 30/9 (khách: "trang luyện tập click Bắt đầu làm đề nó không cho làm") — lệnh CHỈ ĐỌC,
 * dò đúng lý do vì sao bấm vào 1 đề ở trang Luyện tập mà không vào được màn làm bài.
 *
 * Dò theo ĐÚNG thứ tự các cửa mà luồng thật đi qua (App\Services\AttemptService::startOrResume()
 * -> Student\AssessmentService::buildTakeData() -> Student\AssessmentController::take()), nhưng
 * KHÔNG tạo lượt làm bài nào — chạy bao nhiêu lần cũng không đụng dữ liệu.
 *
 * Dùng: php artisan practice:diagnose {email tài khoản đang bấm thử}
 */
class PracticeDiagnose extends Command
{
    protected $signature = 'practice:diagnose {email? : Email tài khoản đang bấm thử (bỏ trống = chỉ soi dữ liệu đề)}';

    protected $description = 'Dò lý do "bấm Bắt đầu làm đề mà không vào được" ở trang Luyện tập (chỉ đọc).';

    public function __construct(private AttemptService $attemptService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->line('── 1. Cột dữ liệu các bản cập nhật gần đây ──');
        foreach ([
            ['questions', 'display_order', 'php artisan migrate (bản 30/9 — ưu tiên hiển thị câu hỏi)'],
            ['courses', 'session_count_max', 'php artisan migrate (bản 30/9 — số buổi theo khoảng)'],
        ] as [$table, $column, $hint]) {
            $has = Schema::hasColumn($table, $column);
            $this->line(sprintf('  %s.%-18s : %s', $table, $column, $has ? 'CÓ' : 'THIẾU -> chạy '.$hint));
        }
        $this->newLine();

        $user = null;
        if ($email = $this->argument('email')) {
            $user = User::where('email', $email)->first();
            if ($user === null) {
                $this->error('Không tìm thấy tài khoản '.$email);

                return self::FAILURE;
            }
            $this->line('── 2. Tài khoản đang bấm thử ──');
            $this->line('  '.$user->name.' <'.$user->email.'>  ·  vai trò: '.($user->roles->pluck('name')->implode(', ') ?: '(không có vai trò nào)'));
            $this->newLine();
        }

        // Đúng tập đề mà trang Luyện tập công khai liệt kê: type=practice + đã phát hành.
        $assessments = Assessment::query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->withCount(['items', 'answerKeys', 'codingItems'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $this->line('── 3. Các đề đang hiện ở trang Luyện tập ('.$assessments->count().' đề) ──');

        if ($assessments->isEmpty()) {
            $this->warn('  Không có đề nào type=practice + status=published. Trang Luyện tập sẽ không có thẻ đề nào để bấm.');

            return self::SUCCESS;
        }

        foreach ($assessments as $a) {
            $pdfMode = $a->isPdfMode();
            $itemCount = (int) $a->items_count;
            $keyCount = (int) $a->answer_keys_count;
            $codingCount = (int) $a->coding_items_count;
            $maxAttempts = $a->resubmission_policy['max_attempts'] ?? null;

            $this->newLine();
            $this->line(sprintf('  [#%d] %s', $a->id, $a->title));
            $this->line(sprintf('        kiểu nội dung: %s · thời lượng: %s · tổng điểm: %s · số lượt cho phép: %s',
                $pdfMode ? 'đề PDF + phiếu đáp án' : 'câu hỏi rời',
                $a->duration_minutes ? $a->duration_minutes.' phút' : 'không giới hạn',
                $a->total_points ?: '0',
                $maxAttempts ?? 'không giới hạn',
            ));
            $this->line(sprintf('        số câu: %d câu hỏi rời · %d ô đáp án (đề PDF) · %d câu lập trình (đề PDF)',
                $itemCount, $keyCount, $codingCount));

            // ── Cửa 1: đề có gì để làm không (đây là lý do phổ biến nhất) ──
            if ($pdfMode) {
                if (blank($a->pdf_path)) {
                    $this->error('        ✗ CHẶN: đề PDF nhưng CHƯA tải tệp PDF lên -> mở màn làm bài ra là trắng, không có đề để đọc.');
                }
                if ($keyCount === 0 && $codingCount === 0) {
                    $this->error('        ✗ CHẶN: chưa nhập ô đáp án nào và cũng không có câu lập trình -> màn làm bài không có gì để điền.');
                }
            } elseif ($itemCount === 0) {
                $this->error('        ✗ CHẶN: đề chưa có câu hỏi nào -> vào màn làm bài là trang rỗng, không làm được gì.');
            }

            if ($user === null) {
                continue;
            }

            // ── Cửa 2: lượt đang làm dở + đã hết giờ chưa ──
            $inProgress = Attempt::query()
                ->where('user_id', $user->id)
                ->where('assessment_id', $a->id)
                ->where('status', AttemptStatus::InProgress->value)
                ->latest('started_at')
                ->first();

            if ($inProgress !== null) {
                $expired = $this->attemptService->isExpired($inProgress);
                $this->line(sprintf('        lượt đang làm dở: #%d (mở lúc %s)%s',
                    $inProgress->id,
                    $inProgress->started_at,
                    $expired ? ' — ĐÃ HẾT GIỜ' : '',
                ));

                if ($expired) {
                    $this->warn('        ⚠ Bấm vào đề này sẽ bị tự nộp lượt cũ rồi ĐẨY SANG TRANG KẾT QUẢ, không phải màn làm bài.');
                    $this->warn('          (bấm lần thứ hai mới mở được lượt mới — trừ khi hết số lượt cho phép, xem dòng dưới)');
                }
            }

            // ── Cửa 3: hết số lượt làm lại ──
            $submitted = Attempt::query()
                ->where('user_id', $user->id)
                ->where('assessment_id', $a->id)
                ->whereNotNull('submitted_at')
                ->count();

            if ($maxAttempts !== null && $submitted >= (int) $maxAttempts) {
                $this->error(sprintf('        ✗ CHẶN: đã nộp %d/%s lượt -> màn "Không mở được lượt làm bài" với lý do hết lượt.',
                    $submitted, $maxAttempts));
            } elseif ($submitted > 0) {
                $this->line(sprintf('        đã nộp %d lượt trước đó (vẫn còn được làm lại)', $submitted));
            }
        }

        $this->newLine();
        $this->line('── 4. Ghi chú ──');
        $this->line('  · Dòng có dấu ✗ là lý do THẬT khiến bấm vào không làm được.');
        $this->line('  · Không có dòng ✗ nào mà vẫn không vào được thì xem 30 dòng cuối của storage/logs/laravel.log.');

        return self::SUCCESS;
    }
}

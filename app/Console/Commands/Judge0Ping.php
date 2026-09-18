<?php

namespace App\Console\Commands;

use App\Services\CodeJudgingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SỬA 18/9 (khách: "ghi nhận bài làm máy chấm vẫn không chấm được") — kiểm tra máy chấm bằng
 * MỘT lệnh, thay vì nộp thử một bài rồi mò trong storage/logs/laravel.log xem lỗi gì.
 *
 * Chạy: php artisan judge0:ping
 *
 * Lệnh làm đúng 3 bước, dừng ngay ở bước hỏng đầu tiên và nói rõ phải sửa gì:
 *   1. Gọi GET /about  -> có mở được cổng tới Judge0 không (đường hầm SSH/máy chấm đã bật chưa).
 *   2. Gọi GET /languages -> token đúng chưa, và 2 language_id trong config có thật không.
 *   3. Chạy thử 1 chương trình C++ in "OK" -> toàn bộ đường chấm chạy được từ đầu tới cuối.
 *
 * Nhắc lại bối cảnh (xem config/judge0.php): trên máy lập trình, Judge0 KHÔNG chạy cục bộ —
 * phải mở đường hầm trước thì 127.0.0.1:2358 mới có gì để gọi:
 *     ssh -N -L 2358:127.0.0.1:2358 root@<IP-VPS>
 */
class Judge0Ping extends Command
{
    protected $signature = 'judge0:ping';

    protected $description = 'Kiểm tra máy chấm Judge0: mở được cổng chưa, token đúng chưa, chạy thử 1 chương trình.';

    public function handle(CodeJudgingService $judging): int
    {
        $baseUrl = rtrim((string) config('judge0.base_url'), '/');
        $authHeader = (string) config('judge0.auth_header');
        $token = (string) config('judge0.auth_token');

        $this->line('Máy chấm: <options=bold>'.$baseUrl.'</>');
        // CỐ Ý không in token ra màn hình/log — chỉ nói đã có hay chưa.
        $this->line('Token   : '.($token !== '' ? 'đã cấu hình ('.strlen($token).' ký tự)' : '<fg=red>CHƯA cấu hình (JUDGE0_AUTH_TOKEN)</>'));
        $this->newLine();

        $client = fn () => Http::baseUrl($baseUrl)
            ->withHeaders([$authHeader => $token])
            ->connectTimeout((int) config('judge0.connect_timeout'))
            ->timeout(15);

        // ── Bước 1: có tới được máy chấm không ────────────────────────────────────────────
        try {
            $about = $client()->get('/about');
        } catch (Throwable $e) {
            $this->error('✗ Không mở được kết nối tới '.$baseUrl);
            $this->line('  '.$e->getMessage());
            $this->newLine();
            $this->warn('Thường gặp: máy chấm chưa bật, hoặc đang chạy ở máy khác mà chưa mở đường hầm SSH.');
            $this->line('  Trên máy lập trình, mở đường hầm rồi chạy lại lệnh này:');
            $this->line('  <options=bold>ssh -N -L 2358:127.0.0.1:2358 root@<IP-VPS></>');

            return self::FAILURE;
        }

        if ($about->failed()) {
            $this->error('✗ Máy chấm trả HTTP '.$about->status().' ở /about');
            $this->line('  '.$about->body());

            return self::FAILURE;
        }

        $this->info('✓ Bước 1 — kết nối được tới máy chấm.');

        // ── Bước 2: token đúng chưa + 2 language_id có thật không ─────────────────────────
        $languages = $client()->get('/languages');

        if ($languages->status() === 401 || $languages->status() === 403) {
            $this->error('✗ Bước 2 — máy chấm từ chối (HTTP '.$languages->status().'): sai token.');
            $this->line('  JUDGE0_AUTH_TOKEN trong .env phải khớp AUTHN_TOKEN trong judge0.conf trên máy chấm.');

            return self::FAILURE;
        }

        if ($languages->failed()) {
            $this->error('✗ Bước 2 — /languages trả HTTP '.$languages->status().': '.$languages->body());

            return self::FAILURE;
        }

        $available = collect((array) $languages->json())->pluck('name', 'id');
        $this->info('✓ Bước 2 — token hợp lệ, máy chấm có '.$available->count().' ngôn ngữ.');

        foreach ((array) config('judge0.languages') as $key => $id) {
            $name = $available[$id] ?? null;
            if ($name === null) {
                $this->warn("  ! '{$key}' đang trỏ language_id={$id} — máy chấm KHÔNG có id này. Xem lại config/judge0.php.");
            } else {
                $this->line("  · {$key} -> #{$id} {$name}");
            }
        }

        // ── Bước 3: chạy thử thật ─────────────────────────────────────────────────────────
        $source = "#include <iostream>\nint main(){ int a,b; std::cin>>a>>b; std::cout<<a+b; return 0; }";

        try {
            $result = $judging->run($source, 'cpp', "2 3\n", 5000, 262144);
        } catch (Throwable $e) {
            $this->error('✗ Bước 3 — không chạy thử được: '.$e->getMessage());

            return self::FAILURE;
        }

        $output = trim($result['output']);

        if ($output !== '5') {
            $this->error('✗ Bước 3 — chạy xong nhưng kết quả sai. Mong đợi "5", nhận được "'.$output.'".');
            $this->line('  Trạng thái: '.$result['statusLabel']);
            if ($result['compileOutput']) {
                $this->line('  Lỗi biên dịch: '.$result['compileOutput']);
            }
            if ($result['stderr']) {
                $this->line('  Lỗi khi chạy: '.$result['stderr']);
            }

            return self::FAILURE;
        }

        $this->info('✓ Bước 3 — chạy thử đúng (2 + 3 = 5) trong '.($result['time'] ?? '?').'s.');
        $this->newLine();
        $this->info('Máy chấm hoạt động bình thường.');

        return self::SUCCESS;
    }
}

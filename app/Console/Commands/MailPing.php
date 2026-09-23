<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SỬA 23/9 (khách: "không thấy email được gửi về") — kiểm tra đường gửi thư bằng 1 lệnh, thay
 * vì đăng ký thử rồi mò trong storage/logs/laravel.log.
 *
 *   php artisan mail:ping                      (xem cấu hình + thử mở TỪNG cổng)
 *   php artisan mail:ping ban@gmail.com        (gửi thật 1 thư thử)
 *
 * KHÔNG in mật khẩu ra màn hình, chỉ nói đã điền hay chưa và dài bao nhiêu ký tự.
 */
class MailPing extends Command
{
    protected $signature = 'mail:ping {to? : Email nhận thư thử}';

    protected $description = 'Kiểm tra cấu hình gửi thư: cổng có mở không, đăng nhập SMTP được không, gửi thử 1 thư.';

    public function handle(): int
    {
        $default = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        $this->line('Kiểu gửi : '.$default);
        $this->line('Gửi từ   : '.$from);

        if (in_array($default, ['log', 'array'], true)) {
            $this->error('✗ MAIL_MAILER đang là "'.$default.'" — thư chỉ ghi vào log, không ai nhận được.');
            $this->line('  Sửa .env thành MAIL_MAILER=smtp_auto rồi chạy: php artisan config:clear && php artisan config:cache');

            return self::FAILURE;
        }

        // 'smtp_auto' là chuỗi dự phòng (465 -> 587), xem config/mail.php. Soi TỪNG đường một.
        $chain = (array) (config("mail.mailers.{$default}.mailers") ?: [$default]);
        $reachable = [];

        foreach ($chain as $name) {
            $cfg = (array) config("mail.mailers.{$name}");
            $host = (string) ($cfg['host'] ?? '');
            $port = (int) ($cfg['port'] ?? 0);
            $scheme = (string) ($cfg['scheme'] ?? 'smtp');
            $user = (string) ($cfg['username'] ?? '');
            $pass = (string) ($cfg['password'] ?? '');

            $this->newLine();
            $this->line("[{$name}] {$host}:{$port} ({$scheme})");
            $this->line('  Tài khoản: '.($user !== '' ? $user : '<fg=red>chưa điền</>')
                .' · Mật khẩu: '.($pass !== '' ? 'đã điền ('.strlen($pass).' ký tự)' : '<fg=red>CHƯA điền</>'));

            $start = microtime(true);
            $sock = @fsockopen(($port === 465 ? 'ssl://' : '').$host, $port, $errNo, $errStr, 10);

            if ($sock === false) {
                $this->line('  <fg=red>✗ Không mở được cổng</> — '.$errStr.' ('.$errNo.')');

                continue;
            }

            $banner = trim((string) fgets($sock, 512));
            fclose($sock);
            $reachable[] = $name;
            $this->line('  <fg=green>✓ Mở được</> sau '.round((microtime(true) - $start) * 1000).'ms — '.mb_substr($banner, 0, 70));
        }

        $this->newLine();

        if ($reachable === []) {
            $this->error('✗ Không cổng nào mở được — mạng đang dùng chặn cổng gửi thư ra ngoài.');
            $this->line('  · Thử lại trên VPS: thực tế thư gửi từ VPS chứ không phải máy lập trình.');
            $this->line('  · Nếu VPS cũng chặn: mở ticket nhờ nhà cung cấp mở cổng 465 và 587 chiều ra.');
            $this->line('  · Hoặc chuyển sang dịch vụ gửi thư qua API cổng 443 (Brevo, Resend) — không bị chặn.');

            return self::FAILURE;
        }

        $this->info('✓ Đường dùng được: '.implode(', ', $reachable));

        $to = $this->argument('to');

        if (blank($to)) {
            $this->line('Muốn gửi thử một thư: <options=bold>php artisan mail:ping ban@gmail.com</>');

            return self::SUCCESS;
        }

        try {
            Mail::raw(
                "Đây là thư kiểm tra từ hệ thống Ôn Thi 360.\nGửi lúc: ".now()->format('d/m/Y H:i:s'),
                fn ($m) => $m->to($to)->subject('[Ôn Thi 360] Thư kiểm tra gửi thư'),
            );
        } catch (Throwable $e) {
            $this->error('✗ Không gửi được: '.$e->getMessage());
            $this->newLine();
            $this->warn('Đọc câu lỗi ở trên để biết hướng sửa:');
            $this->line('  · "Authentication failed" hoặc "535" -> sai tài khoản/mật khẩu hộp thư. Tài khoản phải là ĐỊA CHỈ EMAIL ĐẦY ĐỦ.');
            $this->line('  · "Connection could not be established" -> cổng bị chặn, xem phần trên.');
            $this->line('  · "certificate verify failed" -> MAIL_HOST phải khớp tên trên chứng chỉ của máy chủ thư.');
            $this->line('  · "550" hoặc "Sender ... not allowed" -> MAIL_FROM_ADDRESS phải trùng hộp thư đang đăng nhập.');

            return self::FAILURE;
        }

        $this->info('✓ Đã gửi thư tới '.$to.' — kiểm tra cả hộp thư đến lẫn thư rác.');
        $this->line('  Thư rơi vào thư rác nghĩa là còn thiếu bản ghi SPF/DKIM/DMARC của tên miền.');

        return self::SUCCESS;
    }
}

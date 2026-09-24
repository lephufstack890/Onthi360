<?php

namespace App\Console\Commands;

use App\Services\Judge0Client;
use Illuminate\Console\Command;
use Throwable;

/**
 * SỬA 24/9 (khách: "chấm lâu quá, khách muốn 3-6s/bài") — THƯỚC ĐO máy chấm.
 *
 * Gọi thẳng Judge0 đúng cách mà lúc chấm thật đang gọi, rồi bóc tách xem giây nào đi đâu:
 * bao nhiêu cho biên dịch, bao nhiêu là phụ phí mỗi lần nộp, và máy chấm có THẬT SỰ chạy
 * song song hay không.
 *
 *   php artisan judge0:bench                 (20 test, C++ có bits/stdc++.h — giống bài thật)
 *   php artisan judge0:bench --tests=20 --light   (C++ chỉ <iostream> — để so chi phí thư viện)
 *
 * Con số quan trọng nhất ở cuối: "Mức chạy song song thật". Bằng ~1 nghĩa là COUNT=1 (xếp
 * hàng nối đuôi), bằng ~2 nghĩa là COUNT=2 đã ăn.
 */
class Judge0Bench extends Command
{
    protected $signature = 'judge0:bench {--tests=20 : Số test giả lập} {--light : Dùng <iostream> thay cho bits/stdc++.h}';

    protected $description = 'Đo tốc độ máy chấm Judge0: biên dịch, phụ phí mỗi lần nộp, mức chạy song song.';

    public function handle(Judge0Client $client): int
    {
        $n = max(2, (int) $this->option('tests'));
        $light = (bool) $this->option('light');

        $header = $light ? '#include <iostream>' : '#include <bits/stdc++.h>';
        $source = $header."\nusing namespace std;\nint main(){ios_base::sync_with_stdio(false);cin.tie(nullptr);long long a,b;cin>>a>>b;cout<<a+b<<\"\\n\";return 0;}\n";

        // Lấy ĐÚNG mã ngôn ngữ mà CodeJudgingService đang gửi, không gán cứng — cài lại Judge0
        // bằng ảnh khác là số này đổi (xem config/judge0.php).
        $languageId = \App\Services\CodeJudgingService::languageId('cpp');

        if ($languageId === null) {
            $this->error('Chưa cấu hình mã ngôn ngữ C++ trong config/judge0.php.');

            return self::FAILURE;
        }

        $one = [[
            'source_code' => $source,
            'language_id' => $languageId,
            'stdin' => "3 5\n",
            'cpu_time_limit' => 5,
            'wall_time_limit' => 10,
            'memory_limit' => 262144,
        ]];

        $this->line('Thư viện dùng : '.($light ? '<iostream>' : 'bits/stdc++.h'));
        $this->line('Số test       : '.$n);
        $this->newLine();

        try {
            $this->line('Đang hâm nóng máy chấm…');
            $client->runBatch($one);

            $this->line('Đo 1 lần nộp…');
            $t0 = microtime(true);
            $r1 = $client->runBatch($one);
            $single = microtime(true) - $t0;

            $this->line('Đo '.$n.' lần nộp trong 1 lô…');
            $many = array_fill(0, $n, $one[0]);
            $t0 = microtime(true);
            $rn = $client->runBatch($many);
            $batch = microtime(true) - $t0;
        } catch (Throwable $e) {
            $this->error('Không gọi được máy chấm: '.$e->getMessage());

            return self::FAILURE;
        }

        $cpuTime = (float) ($r1[0]['time'] ?? 0);

        $this->newLine();
        $this->line('── Kết quả ──');
        $this->line(sprintf('  1 lần nộp                     : %6.2f s', $single));
        $this->line(sprintf('    · trong đó CHẠY chương trình: %6.2f s', $cpuTime));
        $this->line(sprintf('    · còn lại (biên dịch + phụ phí): %6.2f s', max(0, $single - $cpuTime)));
        $this->newLine();
        $this->line(sprintf('  %d lần nộp trong 1 lô          : %6.2f s', $n, $batch));
        $this->line(sprintf('  Nếu xếp hàng nối đuôi thì mất  : %6.2f s', $single * $n));

        $parallel = $batch > 0 ? ($single * $n) / $batch : 0;

        $this->newLine();
        $this->line(sprintf('  Mức chạy song song thật        : %.1f×', $parallel));

        if ($parallel < 1.4) {
            $this->warn('  ⚠ Máy chấm đang chạy TUẦN TỰ — COUNT vẫn là 1, cấu hình chưa ăn.');
        } elseif ($parallel < 2.4) {
            $this->info('  ✓ Đang chạy 2 luồng — COUNT=2 đã ăn.');
        } else {
            $this->info(sprintf('  ✓ Đang chạy khoảng %d luồng.', (int) round($parallel)));
        }

        $this->newLine();
        $this->line('  Đây là thời gian chấm 1 bài '.$n.' test theo cách hiện tại: '.sprintf('%.1f s', $batch));
        $this->line('  (chưa kể 2 chặng gọi của CodeJudgingService — thực tế trên web còn cao hơn chút.)');

        $ok = collect($rn)->every(fn ($r) => (int) ($r['status']['id'] ?? 0) === 3);
        if (! $ok) {
            $this->warn('  ⚠ Có bài nộp thử không ra trạng thái "Accepted" — xem lại giới hạn của máy chấm.');
        }

        return self::SUCCESS;
    }
}

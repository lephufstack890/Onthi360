<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImagesOptimize extends Command
{
    protected $signature = 'images:optimize
        {--ghi : Ghi đè thật (bỏ cờ này thì chỉ liệt kê, không đụng tệp nào)}
        {--rong=1600 : Chiều rộng tối đa giữ lại}
        {--toi-thieu=80 : Bỏ qua tệp nhỏ hơn số KB này}';

    protected $description = 'Thu nhỏ và nén lại ảnh đã tải lên trong storage/app/public (mặc định chỉ liệt kê).';

    public function handle(): int
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->error('Máy chủ chưa bật phần mở rộng GD của PHP nên không nén được ảnh.');
            $this->line('Kiểm tra bằng:  php -m | grep -i gd');
            $this->line('Cài trên Ubuntu/Debian:  sudo apt install php8.4-gd && sudo systemctl restart php8.4-fpm');

            return self::FAILURE;
        }

        $write = (bool) $this->option('ghi');
        $maxWidth = max(200, (int) $this->option('rong'));
        $minBytes = max(0, (int) $this->option('toi-thieu')) * 1024;

        $root = rtrim(Storage::disk('public')->path(''), '/');
        if (! is_dir($root)) {
            $this->error('Không thấy thư mục '.$root);

            return self::FAILURE;
        }

        $this->line($write ? 'CHẾ ĐỘ GHI ĐÈ THẬT' : 'CHẾ ĐỘ THỬ — không tệp nào bị sửa (thêm --ghi để ghi thật)');
        $this->line('Thư mục: '.$root);
        $this->newLine();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $seen = 0;
        $touched = 0;
        $before = 0;
        $after = 0;

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            if (! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            $size = $file->getSize();
            if ($size < $minBytes) {
                continue;
            }

            $seen++;
            $path = $file->getPathname();
            $short = ltrim(str_replace($root, '', $path), '/');

            if (! $write) {
                // Thử nén trên bản sao tạm để biết trước tiết kiệm được bao nhiêu.
                $tmp = tempnam(sys_get_temp_dir(), 'oi-img-');
                copy($path, $tmp);
                $result = ImageOptimizer::shrinkInPlace($tmp, $maxWidth);
                @unlink($tmp);
            } else {
                $result = ImageOptimizer::shrinkInPlace($path, $maxWidth);
            }

            if ($result === null) {
                continue;
            }

            [$was, $now] = $result;
            $touched++;
            $before += $was;
            $after += $now;

            $this->line(sprintf(
                '  %6s KB -> %6s KB  (-%d%%)  %s',
                number_format($was / 1024, 0),
                number_format($now / 1024, 0),
                100 - (int) round($now * 100 / $was),
                $short
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Soi %d tệp, %s %d tệp: %s MB -> %s MB (nhẹ đi %s MB).',
            $seen,
            $write ? 'đã nén' : 'có thể nén',
            $touched,
            number_format($before / 1048576, 1),
            number_format($after / 1048576, 1),
            number_format(($before - $after) / 1048576, 1)
        ));

        if (! $write && $touched > 0) {
            $this->newLine();
            $this->line('Chạy lại với --ghi để nén thật:  php artisan images:optimize --ghi');
        }

        return self::SUCCESS;
    }
}

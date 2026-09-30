<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * SỬA 30/9 (9) — DÒ DIRECTIVE BLADE BỊ DÍNH NGAY SAU MỘT CHỮ.
 *
 * Hôm nay trang "Làm bài" lỗi 500 vì đúng một dòng:
 *
 *     ... test đúng@endif</span>
 *
 * Blade nhận directive bằng biểu thức có \B đứng trước @, nghĩa là ký tự ngay trước @
 * KHÔNG được là chữ cái/chữ số. "đúng@endif" dính sau chữ "g" nên Blade KHÔNG coi đó là
 * directive: nó in ra nguyên văn "@endif", còn @if ở phía trên thì mất lệnh đóng — PHP
 * biên dịch ra "unexpected end of file, expecting endif" và cả trang chết.
 *
 * Đếm @if/@endif bằng mắt KHÔNG bắt được lỗi này, vì số lượng vẫn cân bằng — cái sai nằm ở
 * chỗ Blade có CHỊU nhận directive đó hay không.
 *
 * Dùng:  php artisan blade:lint
 * Nên chạy kèm "php artisan view:cache" trước mỗi lần deploy: lệnh đó biên dịch mọi view
 * nên bắt được lỗi cấu trúc, còn lệnh này bắt được directive bị nuốt mà vẫn biên dịch trót lọt.
 */
class BladeLint extends Command
{
    protected $signature = 'blade:lint {--path= : Thư mục cần soi (mặc định resources/views)}';

    protected $description = 'Dò directive Blade bị dính ngay sau một chữ (Blade sẽ không nhận ra).';

    /** Các directive hay bị dính nhất — đều là loại làm vỡ trang nếu bị nuốt. */
    private const DIRECTIVES = [
        'if', 'endif', 'else', 'elseif', 'unless', 'endunless',
        'foreach', 'endforeach', 'forelse', 'empty', 'endforelse',
        'for', 'endfor', 'while', 'endwhile', 'php', 'endphp',
        'isset', 'endisset', 'auth', 'endauth', 'guest', 'endguest',
        'switch', 'case', 'endswitch', 'section', 'endsection', 'push', 'endpush',
        'include', 'extends', 'yield', 'error', 'enderror', 'class', 'checked', 'selected',
    ];

    public function handle(): int
    {
        $root = $this->option('path') ?: resource_path('views');

        if (! is_dir($root)) {
            $this->error('Không thấy thư mục '.$root);

            return self::FAILURE;
        }

        $pattern = '/(\w)@('.implode('|', self::DIRECTIVES).')\b/u';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $hits = 0;
        $seen = 0;

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $seen++;
            $source = (string) file_get_contents($file->getPathname());

            // Bỏ chú thích Blade nhưng GIỮ NGUYÊN số dòng, để chỗ báo lỗi trỏ đúng dòng.
            $clean = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($m) => preg_replace('/[^\n]/', ' ', $m[0]), $source);

            if (! preg_match_all($pattern, $clean, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as $i => [$text, $offset]) {
                $hits++;
                $line = substr_count(substr($clean, 0, $offset), "\n") + 1;
                $this->line(sprintf(
                    '  %s:%d  "%s@%s" — Blade sẽ KHÔNG nhận directive này',
                    str_replace(base_path().'/', '', $file->getPathname()),
                    $line,
                    $matches[1][$i][0],
                    $matches[2][$i][0]
                ));
            }
        }

        $this->newLine();

        if ($hits > 0) {
            $this->error(sprintf('Soi %d tệp, thấy %d chỗ dính. Chèn một khoảng trắng hoặc xuống dòng trước dấu @ là xong.', $seen, $hits));

            return self::FAILURE;
        }

        $this->info(sprintf('Soi %d tệp, không có directive nào bị dính sau chữ.', $seen));

        return self::SUCCESS;
    }
}

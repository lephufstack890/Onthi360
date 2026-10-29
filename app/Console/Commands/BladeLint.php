<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BladeLint extends Command
{
    protected $signature = 'blade:lint {--path= : Thư mục cần soi (mặc định resources/views)}';

    protected $description = 'Dò directive Blade bị dính ngay sau một chữ (Blade sẽ không nhận ra).';

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

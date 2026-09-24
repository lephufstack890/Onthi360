<?php

namespace App\Console\Commands;

use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Services\BundledJudgePackage;
use App\Services\CodeJudgingService;
use App\Services\Judge0Client;
use Illuminate\Console\Command;
use Throwable;

/**
 * SỬA 24/9 (khách: "sao giờ chấm sai hết thế này") — SOI TẬN MẮT cái gói chấm gộp trả về.
 *
 * compare-bundled chỉ nói ĐÚNG hay SAI. Lệnh này in ra nguyên văn những gì hộp cách ly của
 * Judge0 nhả ra: trạng thái, thông báo biên dịch, stdout thô, và từng test đọc được — để biết
 * script chạy tới đâu thì hỏng, thay vì đoán.
 *
 *   php artisan judge0:dump-bundled 23
 *   php artisan judge0:dump-bundled 23 --tests=3   (chỉ lấy 3 test đầu cho gọn)
 *   php artisan judge0:dump-bundled 23 --save=/tmp/goi.zip   (ghi luôn gói ZIP ra để mở xem)
 */
class Judge0DumpBundled extends Command
{
    protected $signature = 'judge0:dump-bundled
        {question : ID câu hỏi Lập trình}
        {--answer= : ID attempt_answers lấy mã nguồn}
        {--tests=3 : Chỉ gửi bấy nhiêu test đầu}
        {--save= : Ghi gói ZIP ra đường dẫn này}';

    protected $description = 'In nguyên văn kết quả thô của một lượt chấm gộp, để dò lỗi.';

    public function handle(Judge0Client $client): int
    {
        $question = Question::find((int) $this->argument('question'));

        if ($question === null) {
            $this->error('Không tìm thấy câu hỏi.');

            return self::FAILURE;
        }

        $config = $question->grading_config ?? [];
        $all = collect($config['test_cases'] ?? [])
            ->map(fn ($tc) => ['input' => (string) ($tc['input'] ?? ''), 'expected_output' => (string) ($tc['output'] ?? '')])
            ->all();

        if ($all === []) {
            $this->error('Câu này chưa có test case nào.');

            return self::FAILURE;
        }

        $testCases = array_slice($all, 0, max(1, (int) $this->option('tests')));

        $answerQuery = AttemptAnswer::query()->whereNotNull('code_source');
        if (filled($this->option('answer'))) {
            $answerQuery->whereKey((int) $this->option('answer'));
        } else {
            $answerQuery->where('question_id', $question->id)->latest('id');
        }
        $answer = $answerQuery->first();

        if ($answer === null) {
            $this->error('Chưa có bài làm nào của câu này để chấm thử.');

            return self::FAILURE;
        }

        $langKey = CodeJudgingService::languageKey($answer->language);

        if (! BundledJudgePackage::supports($langKey)) {
            $this->error('Ngôn ngữ "'.$answer->language.'" không chấm gộp được.');

            return self::FAILURE;
        }

        $perTest = (int) max(1, ceil(((int) ($config['time_limit_ms'] ?? 5000)) / 1000));
        $count = count($testCases);

        $package = new BundledJudgePackage();
        $zip = $package->build(
            $answer->code_source,
            (string) $langKey,
            $testCases,
            $perTest,
            $perTest * $count + 5,
            65536
        );

        if (filled($this->option('save'))) {
            file_put_contents((string) $this->option('save'), $zip);
            $this->line('Đã ghi gói ra: '.$this->option('save'));
        }

        $this->line('Câu       : #'.$question->id.' — '.$question->title);
        $this->line('Bài làm   : câu trả lời #'.$answer->id.' ('.$langKey.')');
        $this->line('Số test   : '.$count.' · mỗi test '.$perTest.'s · gói '.number_format(strlen($zip)).' byte');
        $this->newLine();

        try {
            $result = $client->runBundled($zip, (float) ($perTest * $count + 20), (float) ($perTest * $count + 40), 262144);
        } catch (Throwable $e) {
            $this->error('Gọi máy chấm hỏng: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('── Máy chấm trả về ──');
        $this->line('  Trạng thái     : '.(int) ($result['status']['id'] ?? 0).' — '.(string) ($result['status']['description'] ?? '?'));
        $this->line('  CPU / bộ nhớ   : '.($result['time'] ?? '?').'s / '.($result['memory'] ?? '?').'KB');
        $this->newLine();

        $this->line('── compile_output ──');
        $this->line($this->box((string) ($result['compile_output'] ?? '')));
        $this->newLine();

        $this->line('── stderr ──');
        $this->line($this->box((string) ($result['stderr'] ?? '')));
        $this->newLine();

        $this->line('── stdout THÔ (tối đa 2000 ký tự) ──');
        $this->line($this->box((string) ($result['stdout'] ?? ''), 2000));
        $this->newLine();

        $rows = $package->parse((string) ($result['stdout'] ?? ''));

        $this->line('── Bóc ra được '.count($rows).'/'.$count.' test ──');

        foreach ($rows as $i => $row) {
            $this->line(sprintf(
                '  test %d · rc=%d ms=%d bytes=%d',
                $row['index'],
                $row['exitCode'],
                $row['ms'],
                $row['bytes']
            ));
            $this->line('      bài in ra : '.json_encode($row['output'], JSON_UNESCAPED_UNICODE));
            $this->line('      đáp án    : '.json_encode($testCases[$i]['expected_output'] ?? '', JSON_UNESCAPED_UNICODE));
        }

        if ($rows === []) {
            $this->newLine();
            $this->warn('  Không bóc được test nào — mốc phân tách không có trong stdout.');
            $this->warn('  Mốc đang dùng: '.$package->mark());
        }

        return self::SUCCESS;
    }

    private function box(string $text, int $limit = 800): string
    {
        $text = trim($text);

        if ($text === '') {
            return '  (rỗng)';
        }

        if (strlen($text) > $limit) {
            $text = substr($text, 0, $limit)."\n… (cắt bớt)";
        }

        return '  '.str_replace("\n", "\n  ", $text);
    }
}

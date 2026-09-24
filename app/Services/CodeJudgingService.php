<?php

namespace App\Services;

use App\Enums\VerdictStatus;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CodeJudgingService
{
    public function __construct(private readonly Judge0Client $client) {}

    /**
     * @param  array<int, array{input: string, expected_output: string}>  $testCases
     * @return array{verdict: VerdictStatus, isAccepted: bool, details: array<int, array{status: string, time: ?string, memory: ?int, stderr: ?string, compileOutput: ?string}>}
     *
     * @throws RuntimeException
     */
    public function judge(string $sourceCode, ?string $language, array $testCases, int $timeLimitMs, int $memoryLimitKb, ?array $fileIo = null): array
    {
        if ($testCases === []) {
            return ['verdict' => VerdictStatus::SystemError, 'isAccepted' => false, 'details' => []];
        }

        $languageId = self::languageId($language);

        if ($languageId === null || trim($sourceCode) === '') {
            return ['verdict' => VerdictStatus::CompileError, 'isAccepted' => false, 'details' => []];
        }

        $sourceCode = $this->withFileIo($sourceCode, self::languageKey($language), $fileIo);

        $cpuTimeLimit = min((float) config('judge0.max_cpu_time_limit'), max(1.0, $timeLimitMs / 1000));
        $wallTimeLimit = min((float) config('judge0.max_wall_time_limit'), $cpuTimeLimit + 10);
        $memoryLimit = min((int) config('judge0.max_memory_limit_kb'), max(16384, $memoryLimitKb));

        /*
         * SỬA 24/9 (khách: "chấm lâu quá, muốn 3-6s/bài") — thử CHẤM GỘP trước.
         *
         * Đo thật trên máy chủ: 1 bài nộp 3,09 giây mà chạy chương trình chỉ 0,01 giây — phần
         * còn lại là biên dịch + dựng hộp cách ly. Gửi 20 test = 20 bài nộp = 28,9 giây. Chấm
         * gộp gửi đúng 1 bài nộp (biên dịch 1 lần, script tự lặp qua 20 test) -> ~3 giây.
         *
         * Trả null nghĩa là "không gộp được lượt này" (ngôn ngữ lạ, thiếu ext-zip, dữ liệu vào
         * quá to, ngân sách thời gian vượt trần Judge0, hoặc gói chạy ra kết quả không đọc được)
         * — khi đó rơi xuống cách chấm cũ ngay bên dưới. CỐ Ý không ném lỗi: thà chấm chậm còn
         * hơn không chấm được.
         */
        if ((bool) config('judge0.bundled_run')) {
            $bundled = $this->judgeBundled($sourceCode, self::languageKey($language), $testCases, $cpuTimeLimit, $memoryLimit, $fileIo);

            if ($bundled !== null) {
                return $bundled;
            }
        }

        $submissions = array_map(static fn (array $tc) => [
            'source_code' => $sourceCode,
            'language_id' => $languageId,
            'stdin' => $tc['input'],
            'expected_output' => $tc['expected_output'],
            'cpu_time_limit' => $cpuTimeLimit,
            'wall_time_limit' => $wallTimeLimit,
            'memory_limit' => $memoryLimit,
        ], $testCases);

        /*
         * SỬA 23/9 (khách: "chương trình lỗi thì ngừng chấm luôn, đừng chạy qua các test nữa —
         * tốn thời gian và tài nguyên; chương trình chạy được mới chấm test") — chấm 2 chặng:
         *
         *   Chặng 1: nộp ĐÚNG 1 test đầu. Mã không biên dịch được thì dừng ngay, không đụng
         *            tới 19 test còn lại (trước đây bài sai cú pháp vẫn đốt đủ 20 lượt chạy).
         *   Chặng 2: biên dịch ổn mới nộp các test còn lại.
         *
         * Test đầu ở chặng 1 vẫn gửi kèm expected_output nên nó là một test được chấm thật,
         * KHÔNG tốn thêm lượt chạy nào so với cách cũ khi mã chạy được.
         */
        $firstResults = $this->client->runBatch([$submissions[0]]);
        $first = $firstResults[0] ?? null;
        $firstStatusId = (int) ($first['status']['id'] ?? 13);

        // 6 = Compilation Error. Với Python, Judge0 cũng báo lỗi cú pháp ở bước này.
        if ($firstStatusId === 6) {
            return $this->compileErrorResult($first, count($testCases), self::languageKey($language), $fileIo);
        }

        $results = $firstResults;

        if (count($submissions) > 1) {
            $results = array_merge($results, $this->client->runBatch(array_slice($submissions, 1)));
        }

        $verdict = VerdictStatus::Accepted;
        $details = [];

        foreach ($results as $i => $r) {
            $caseVerdict = $this->mapStatus((int) ($r['status']['id'] ?? 13), $r['memory'] ?? null, $memoryLimit);
            $verdict = $this->worseOf($verdict, $caseVerdict);

            $details[] = [
                'index' => $i + 1,
                'isAccepted' => $caseVerdict === VerdictStatus::Accepted,
                'statusLabel' => $caseVerdict->label(),
                'status' => (string) ($r['status']['description'] ?? 'Unknown'),
                'time' => $r['time'] ?? null,
                'memory' => $r['memory'] ?? null,
                'input' => $testCases[$i]['input'] ?? '',
                'expectedOutput' => $testCases[$i]['expected_output'] ?? '',
                'actualOutput' => $r['stdout'] ?? null,
                'stderr' => $r['stderr'] !== null && $r['stderr'] !== '' ? $r['stderr'] : null,
                'compileOutput' => $r['compile_output'] !== null && $r['compile_output'] !== '' ? $r['compile_output'] : null,
            ];
        }

        return [
            'verdict' => $verdict,
            'isAccepted' => $verdict === VerdictStatus::Accepted,
            'details' => $details,
        ];
    }

    /**
     * SỬA 18/9 (khách: "chỗ chạy test không được") — CHẠY THỬ 1 lần với dữ liệu vào học sinh tự
     * gõ, KHÔNG so với đáp án và KHÔNG chấm điểm. Khác judge() ở trên đúng 2 chỗ:
     *   · không gửi 'expected_output' -> Judge0 trả status "Accepted" (id=3) miễn là chương
     *     trình chạy xong không lỗi, không bao giờ ra "Wrong Answer" — đúng ý nghĩa "chạy thử";
     *   · chỉ 1 bài nộp, nên trả thẳng stdout/stderr/compile_output cho ô Output.
     * Cố ý KHÔNG dùng lại judge() với 1 test rỗng: làm vậy Judge0 sẽ so stdout với chuỗi rỗng
     * và báo "Sai kết quả" cho mọi chương trình có in ra màn hình.
     *
     * @return array{ranCleanly: bool, statusLabel: string, output: string, stderr: ?string, compileOutput: ?string, time: ?string, memory: ?int}
     *
     * @throws RuntimeException khi KHÔNG chạy được: chưa có mã nguồn, ngôn ngữ lạ, hoặc không
     *                          gọi được Judge0 (mất mạng/đứt đường hầm/sai token). Nơi gọi tự
     *                          bắt và hiện lý do cho học sinh.
     */
    public function run(string $sourceCode, ?string $language, string $stdin, int $timeLimitMs, int $memoryLimitKb, ?array $fileIo = null): array
    {
        $languageId = self::languageId($language);

        if ($languageId === null) {
            throw new RuntimeException('Ngôn ngữ chưa được hỗ trợ trên máy chấm.');
        }

        if (trim($sourceCode) === '') {
            throw new RuntimeException('Chưa có mã nguồn để chạy.');
        }

        $cpuTimeLimit = min((float) config('judge0.max_cpu_time_limit'), max(1.0, $timeLimitMs / 1000));
        $memoryLimit = min((int) config('judge0.max_memory_limit_kb'), max(16384, $memoryLimitKb));

        $sourceCode = $this->withFileIo($sourceCode, self::languageKey($language), $fileIo);

        $results = $this->client->runBatch([[
            'source_code' => $sourceCode,
            'language_id' => $languageId,
            'stdin' => $stdin,
            'cpu_time_limit' => $cpuTimeLimit,
            'wall_time_limit' => min((float) config('judge0.max_wall_time_limit'), $cpuTimeLimit + 10),
            'memory_limit' => $memoryLimit,
        ]]);

        $r = $results[0] ?? null;

        if ($r === null) {
            throw new RuntimeException('Máy chấm không trả về kết quả nào.');
        }

        $verdict = $this->mapStatus((int) ($r['status']['id'] ?? 13), $r['memory'] ?? null, $memoryLimit);

        return [
            // 'ranCleanly' = chương trình chạy xong bình thường. CỐ Ý không đặt tên 'ok': nơi
            // gọi (PracticeByQuestionService::runOnce()) dùng 'ok' với nghĩa KHÁC — "có kết quả
            // để hiện cho học sinh". Lỗi biên dịch/chạy/quá giờ vẫn trả về đầy đủ để học sinh
            // đọc (ok = true, ranCleanly = false), chỉ khác cái nhãn.
            'ranCleanly' => $verdict === VerdictStatus::Accepted,
            'statusLabel' => $verdict === VerdictStatus::Accepted ? 'Chạy xong' : $verdict->label(),
            'output' => (string) ($r['stdout'] ?? ''),
            'stderr' => $r['stderr'] !== null && $r['stderr'] !== '' ? $r['stderr'] : null,
            'compileOutput' => $r['compile_output'] !== null && $r['compile_output'] !== '' ? $r['compile_output'] : null,
            'time' => $r['time'] ?? null,
            'memory' => $r['memory'] ?? null,
        ];
    }

    /**
     * SỬA 21/9 (khách: "20 test đều sai hết") — gói ZIP khai ngôn ngữ kiểu "cpp14"/"python3"
     * trong khi config/judge0.php chỉ có khoá "cpp"/"python" → trước đây ra null và mọi test bị
     * chấm "Lỗi biên dịch". Quy mọi biến thể về đúng 2 khoá của config.
     */
    public static function languageKey(?string $language): ?string
    {
        $l = strtolower(str_replace([' ', '_', '-'], '', (string) $language));

        return match (true) {
            $l === '' => null,
            str_starts_with($l, 'cpp'), str_starts_with($l, 'c++'), str_starts_with($l, 'g++'), str_starts_with($l, 'gnuc++') => 'cpp',
            str_starts_with($l, 'py') => 'python',
            default => $l,
        };
    }

    public static function languageId(?string $language): ?int
    {
        $key = self::languageKey($language);
        $id = $key === null ? null : config("judge0.languages.{$key}");

        return $id === null ? null : (int) $id;
    }

    /**
     * SỬA 21/9 — đề kiểu thi HSG khai file_io (vd TONG.INP / TONG.OUT): học sinh được phép đọc
     * từ file TONG.INP và ghi ra TONG.OUT (freopen / ifstream / open()). Judge0 chỉ đưa dữ liệu
     * qua stdin và chỉ lấy stdout, nên trước đây bài làm đúng đề vẫn sai 100% test.
     *
     * Cách làm: chèn trước mã học sinh một đoạn chạy TRƯỚC main:
     *   1. đọc hết stdin, ghi ra file INP, rồi nối stdin vào file đó → đọc cin hay đọc file đều được;
     *   2. giữ lại "cửa" stdout gốc; khi chương trình kết thúc, nếu có file OUT thì chép nội dung
     *      ra stdout gốc để Judge0 so với đáp án. Không ghi file thì in màn hình vẫn chấm như cũ.
     * Đề không khai file_io → trả nguyên mã, hành vi không đổi.
     */
    private function withFileIo(string $sourceCode, ?string $languageKey, ?array $fileIo): string
    {
        $clean = static fn ($name) => preg_match('/^[A-Za-z0-9._-]{1,64}$/', (string) $name) ? (string) $name : null;
        $in = $clean(is_array($fileIo) ? ($fileIo['input'] ?? null) : null);
        $out = $clean(is_array($fileIo) ? ($fileIo['output'] ?? null) : null);

        if ($in === null && $out === null) {
            return $sourceCode;
        }

        if ($languageKey === 'cpp') {
            $prelude = "#include <cstdio>\n#include <iostream>\n#include <unistd.h>\n#include <fcntl.h>\n"
                ."namespace onthi360_file_io { struct Guard { int saved = -1;\n"
                ."  Guard() {\n"
                .($in !== null
                    // Chỉ nối stdin sang file khi GHI ĐƯỢC file — không ghi được thì để stdin nguyên như cũ.
                    ? "    if (FILE* f = std::fopen(\"{$in}\", \"wb\")) { char b[65536]; size_t n; while ((n = std::fread(b, 1, sizeof b, stdin)) > 0) std::fwrite(b, 1, n, f); std::fclose(f);\n"
                      ."      if (!std::freopen(\"{$in}\", \"rb\", stdin)) {} }\n"
                    : '')
                .($out !== null ? "    std::remove(\"{$out}\"); saved = dup(1);\n" : '')
                ."  }\n"
                ."  ~Guard() {\n"
                ."    std::cout.flush(); std::fflush(nullptr);\n"
                .($out !== null
                    ? "    if (saved < 0) return; int fd = open(\"{$out}\", O_RDONLY); if (fd < 0) return;\n"
                      ."    char b[65536]; ssize_t n; while ((n = read(fd, b, sizeof b)) > 0) { ssize_t o = 0; while (o < n) { ssize_t w = write(saved, b + o, n - o); if (w <= 0) break; o += w; } }\n"
                      ."    close(fd);\n"
                    : '')
                ."  }\n} guard; }\n"
                ."#line 1\n";

            return $prelude.$sourceCode;
        }

        if ($languageKey === 'python') {
            $inLit = var_export($in, true);
            $outLit = var_export($out, true);
            // Gói gọn 1 dòng để số dòng báo lỗi của học sinh chỉ lệch đúng 1.
            $code = <<<PY
import sys as _s, os as _o, io as _io, gc as _gc, atexit as _a
_IN, _OUT = {$inLit}, {$outLit}
if _IN:
    _d = _s.stdin.buffer.read()
    try:
        with open(_IN, 'wb') as _f: _f.write(_d)
        _s.stdin = open(_IN, 'r')
    except OSError:
        _s.stdin = _io.TextIOWrapper(_io.BytesIO(_d))
_saved = -1
if _OUT:
    try: _o.remove(_OUT)
    except OSError: pass
    _saved = _o.dup(1)
def _fin():
    for _x in _gc.get_objects():
        try:
            if isinstance(_x, _io.IOBase) and not _x.closed and _x.writable(): _x.flush()
        except Exception: pass
    if _saved >= 0 and _o.path.exists(_OUT):
        with open(_OUT, 'rb') as _f: _o.write(_saved, _f.read())
_a.register(_fin)
PY;

            return 'exec('.json_encode($code, JSON_UNESCAPED_SLASHES).")\n".$sourceCode;
        }

        return $sourceCode;
    }

    /**
     * SỬA 23/9 — kết quả khi mã KHÔNG biên dịch được: mọi test đều "chưa chấm", kèm thông báo
     * lỗi đã rút gọn và SỐ DÒNG sai trong mã của học sinh (khách: "lỗi code là báo lỗi ở dòng
     * bao nhiêu").
     *
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     */
    /**
     * SỬA 24/9 — CHẤM GỘP: 1 bài nộp cho cả bài, biên dịch 1 lần.
     *
     * @param  array<int, array{input:string, expected_output:string}>  $testCases
     * @return array{verdict: VerdictStatus, isAccepted: bool, details: array}|null  null = không gộp được, hãy dùng cách cũ
     */
    private function judgeBundled(string $sourceCode, ?string $langKey, array $testCases, float $perTestCpu, int $memoryLimit, ?array $fileIo): ?array
    {
        if (! BundledJudgePackage::supports($langKey) || ! BundledJudgePackage::available()) {
            return null;
        }

        $count = count($testCases);

        $inputBytes = 0;
        foreach ($testCases as $tc) {
            $inputBytes += strlen((string) ($tc['input'] ?? ''));
        }

        // Gói quá to sẽ đụng MAX_EXTRACT_SIZE của Judge0 và hỏng CẢ lượt chấm — thà chấm chậm.
        if ($inputBytes > (int) config('judge0.bundled_max_input_bytes')) {
            return null;
        }

        $perTestSeconds = (int) max(1, ceil($perTestCpu));

        // Ngân sách cho cả gói = thời gian từng test × số test + chỗ cho 1 lần biên dịch.
        // bits/stdc++.h đo được ~2 giây, để rộng 20 giây cho chắc.
        $cpuTimeLimit = $perTestSeconds * $count + 20;
        $maxCpu = (float) config('judge0.max_cpu_time_limit');

        // Vượt trần Judge0 (MAX_CPU_TIME_LIMIT) thì gộp không nổi — bài giới hạn 5 giây × 20
        // test đã là 120 giây, quá 60 giây trần. Quay về chấm từng test.
        if ($cpuTimeLimit > $maxCpu) {
            return null;
        }

        $wallTimeLimit = min((float) config('judge0.max_wall_time_limit'), $cpuTimeLimit + 20);

        $package = new BundledJudgePackage();

        // Ngân sách cho RIÊNG chặng chạy test: đủ cho mọi test đều chạm trần thời gian, cộng
        // 5 giây dư. Hết ngân sách thì script tự ghi nốt các test còn lại thành "quá thời gian"
        // rồi thoát đẹp — thà trả về kết quả đọc được còn hơn để Judge0 giết ngang cả gói.
        $runBudget = $perTestSeconds * $count + 5;

        /*
         * SỬA 24/9 — trần lượng in ra được MANG VỀ của mỗi test.
         *
         * Lấy theo chính đáp án dài nhất của bài (gấp đôi + 64 KB dự phòng): luôn đủ rộng để so
         * khớp đúng, mà bài lặp vô hạn rồi in cũng không nhồi được hàng MB rác qua Judge0. Bài
         * in dài hơn trần này thì chắc chắn KHÔNG khớp đáp án — dài gấp đôi đáp án là sai rồi.
         */
        $longestExpected = 0;
        foreach ($testCases as $tc) {
            $longestExpected = max($longestExpected, strlen((string) ($tc['expected_output'] ?? '')));
        }
        $outputCap = max(65536, $longestExpected * 2 + 65536);

        try {
            $zip = $package->build($sourceCode, (string) $langKey, $testCases, $perTestSeconds, $runBudget, $outputCap);
            $result = $this->client->runBundled($zip, (float) $cpuTimeLimit, $wallTimeLimit, $memoryLimit);
        } catch (Throwable $e) {
            Log::warning('Chấm gộp không chạy được, quay về chấm từng test: '.$e->getMessage());

            return null;
        }

        $statusId = (int) ($result['status']['id'] ?? 13);

        // 6 = Compilation Error — script `compile` thất bại. Dừng luôn, y như cách cũ.
        if ($statusId === 6) {
            return $this->compileErrorResult($result, $count, $langKey, $fileIo);
        }

        $rows = $package->parse((string) ($result['stdout'] ?? ''));

        /*
         * Số test đọc được không khớp số test của bài: script chạy dở chừng (hết ngân sách
         * thời gian cho cả gói), hoặc stdout bị Judge0 cắt vì quá dài. Không đoán bừa — quay về
         * cách chấm cũ để học sinh nhận điểm ĐÚNG, dù chậm.
         */
        if (count($rows) !== $count) {
            Log::warning(sprintf(
                'Chấm gộp đọc được %d/%d test (trạng thái Judge0: %s) — quay về chấm từng test.',
                count($rows),
                $count,
                (string) ($result['status']['description'] ?? '?')
            ));

            return null;
        }

        $verdict = VerdictStatus::Accepted;
        $details = [];

        foreach ($rows as $i => $row) {
            $expected = (string) ($testCases[$i]['expected_output'] ?? '');
            $actual = (string) $row['output'];

            /*
             * SỬA 24/9 (khách: "lỡ học sinh viết chạy vô hạn thì sao") — đọc mã thoát của tiến
             * trình để biết bài hỏng KIỂU GÌ:
             *
             *   124 : script tự ghi vì hết ngân sách chung, chưa kịp chạy test này.
             *   137 : bị SIGKILL — đồng hồ canh giờ ra tay vì chạy quá thời gian cho phép.
             *   153 : bị SIGXFSZ — in ra vượt 4 MB, tức là vừa lặp vô hạn vừa in.
             *   khác: chương trình tự chết (chia 0, tràn mảng, con trỏ hỏng...).
             */
            // Bị cắt bớt lúc mang về -> KHÔNG so khớp chuỗi dở dang, xử luôn là sai kết quả.
            $truncated = ($row['bytes'] ?? 0) > $outputCap;

            $caseVerdict = match (true) {
                in_array($row['exitCode'], [124, 137], true) => VerdictStatus::TimeLimitExceeded,
                $row['exitCode'] !== 0 => VerdictStatus::RuntimeError,
                $truncated => VerdictStatus::WrongAnswer,
                self::outputsMatch($actual, $expected) => VerdictStatus::Accepted,
                default => VerdictStatus::WrongAnswer,
            };

            $note = match (true) {
                $row['exitCode'] === 124 => 'Không chạy test này vì cả lượt chấm đã quá thời gian cho phép.',
                $row['exitCode'] === 137 => 'Chương trình chạy quá thời gian cho phép nên bị dừng.',
                $row['exitCode'] === 153 => 'Chương trình in ra quá nhiều dữ liệu nên bị dừng — thường là do lặp vô hạn mà vẫn in.',
                $truncated => 'Chương trình in ra dài hơn đáp án rất nhiều; phần hiện dưới đây đã bị cắt bớt.',
                default => null,
            };

            $verdict = $this->worseOf($verdict, $caseVerdict);

            $details[] = [
                'index' => $i + 1,
                'isAccepted' => $caseVerdict === VerdictStatus::Accepted,
                'statusLabel' => $caseVerdict->label(),
                'status' => $caseVerdict->label(),
                'time' => $row['ms'] >= 0 ? number_format($row['ms'] / 1000, 3, '.', '') : null,
                // Judge0 chỉ báo mức bộ nhớ cao nhất của CẢ lượt chạy, không tách được từng
                // test — để trống còn hơn ghi một con số không đúng của test đó.
                'memory' => null,
                'input' => $testCases[$i]['input'] ?? '',
                'expectedOutput' => $expected,
                'actualOutput' => $actual,
                'stderr' => $note,
                'compileOutput' => null,
            ];
        }

        return [
            'verdict' => $verdict,
            'isAccepted' => $verdict === VerdictStatus::Accepted,
            'details' => $details,
        ];
    }

    /**
     * SỬA 24/9 — so khớp đáp án Ở MÁY CHỦ MÌNH (chấm gộp không gửi đáp án lên Judge0 nữa).
     *
     * Theo đúng luật Judge0 vẫn dùng bấy lâu nay để hai cách chấm cho cùng kết quả: bỏ qua
     * khác biệt về ký tự xuống dòng (CRLF/LF), khoảng trắng thừa ở CUỐI mỗi dòng, và dòng
     * trống ở cuối tệp. Khoảng trắng GIỮA dòng vẫn tính — "1 2" khác "1  2".
     */
    private static function outputsMatch(string $actual, string $expected): bool
    {
        $normalize = static function (string $text): string {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $lines = explode("\n", $text);
            $lines = array_map(static fn ($line) => rtrim($line, " \t"), $lines);

            while ($lines !== [] && end($lines) === '') {
                array_pop($lines);
            }

            return implode("\n", $lines);
        };

        return $normalize($actual) === $normalize($expected);
    }

    private function compileErrorResult(?array $result, int $testCount, ?string $languageKey, ?array $fileIo): array
    {
        $raw = trim((string) ($result['compile_output'] ?? ''));

        if ($raw === '') {
            $raw = trim((string) ($result['stderr'] ?? ''));
        }

        $parsed = $this->parseCompileError($raw, $languageKey, $fileIo);

        $details = [];
        for ($i = 0; $i < $testCount; $i++) {
            $details[] = [
                'index' => $i + 1,
                'isAccepted' => false,
                'statusLabel' => VerdictStatus::CompileError->label(),
                'status' => 'Compilation Error',
                'time' => null,
                'memory' => null,
                'input' => '',
                'expectedOutput' => '',
                'actualOutput' => null,
                'stderr' => null,
                // Chỉ đính lỗi vào test đầu — 19 dòng lặp lại cùng một thông báo chỉ làm rối.
                'compileOutput' => $i === 0 ? ($raw !== '' ? $raw : null) : null,
            ];
        }

        return [
            'verdict' => VerdictStatus::CompileError,
            'isAccepted' => false,
            'details' => $details,
            // 3 khoá dưới đây để view hiện thẳng "Lỗi ở dòng N" thay vì bắt học sinh tự dò
            // trong log biên dịch, xem partials/practice-coding-result.blade.php.
            'compileError' => $raw !== '' ? $raw : null,
            'errorLine' => $parsed['line'],
            'errorMessage' => $parsed['message'],
        ];
    }

    /**
     * Rút "dòng bao nhiêu, lỗi gì" từ log biên dịch.
     *
     *   C++ : "main.cpp:5:14: error: expected ';' before '}' token"
     *   Py  : "  File \"__init__.py\", line 3\n    print(\n         ^\nSyntaxError: ..."
     *
     * @return array{line: ?int, message: ?string}
     */
    private function parseCompileError(string $raw, ?string $languageKey, ?array $fileIo): array
    {
        if ($raw === '') {
            return ['line' => null, 'message' => null];
        }

        $line = null;
        $message = null;

        if (preg_match('/[^\s:]+:(\d+):(?:\d+:)?\s*(?:fatal\s+)?error:\s*(.+)/i', $raw, $m)) {
            $line = (int) $m[1];
            $message = trim($m[2]);
        } elseif (preg_match('/line\s+(\d+)/i', $raw, $m)) {
            $line = (int) $m[1];

            if (preg_match('/^\s*(\w*(?:Error|Exception))\s*:\s*(.+)$/mi', $raw, $m2)) {
                $message = trim($m2[1].': '.$m2[2]);
            }
        }

        /*
         * Đề có file_io thì withFileIo() chèn thêm mã ở đầu bài làm. Bản C++ đã tự nắn số dòng
         * bằng chỉ thị "#line 1" nên không lệch; bản Python chèn đúng 1 dòng exec(...) nên số
         * dòng báo ra lớn hơn số dòng thật 1 đơn vị — trừ lại cho khớp với mã học sinh thấy.
         */
        if ($line !== null && $languageKey === 'python' && ! empty($fileIo)) {
            $line = max(1, $line - 1);
        }

        if ($message === null) {
            // Không khớp mẫu nào thì lấy dòng đầu tiên có chữ "error" cho dễ đọc.
            foreach (preg_split('/\r?\n/', $raw) as $l) {
                if (stripos($l, 'error') !== false) {
                    $message = trim($l);
                    break;
                }
            }
        }

        return ['line' => $line, 'message' => $message];
    }

    private function mapStatus(int $statusId, ?int $memoryUsedKb, int $memoryLimitKb): VerdictStatus
    {
        return match (true) {
            $statusId === 3 => VerdictStatus::Accepted,
            $statusId === 4 => VerdictStatus::WrongAnswer,
            $statusId === 5 => VerdictStatus::TimeLimitExceeded,
            $statusId === 6 => VerdictStatus::CompileError,
            $statusId >= 7 && $statusId <= 12 => ($memoryUsedKb !== null && $memoryUsedKb >= $memoryLimitKb)
                ? VerdictStatus::MemoryLimitExceeded
                : VerdictStatus::RuntimeError,
            default => VerdictStatus::SystemError,
        };
    }

    private function worseOf(VerdictStatus $a, VerdictStatus $b): VerdictStatus
    {
        $rank = [
            VerdictStatus::CompileError->value => 6,
            VerdictStatus::SystemError->value => 5,
            VerdictStatus::RuntimeError->value => 4,
            VerdictStatus::MemoryLimitExceeded->value => 4,
            VerdictStatus::TimeLimitExceeded->value => 3,
            VerdictStatus::WrongAnswer->value => 2,
            VerdictStatus::Accepted->value => 1,
        ];

        return $rank[$b->value] > $rank[$a->value] ? $b : $a;
    }
}

<?php

namespace App\Services;

use App\Enums\VerdictStatus;
use RuntimeException;

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

        $submissions = array_map(static fn (array $tc) => [
            'source_code' => $sourceCode,
            'language_id' => $languageId,
            'stdin' => $tc['input'],
            'expected_output' => $tc['expected_output'],
            'cpu_time_limit' => $cpuTimeLimit,
            'wall_time_limit' => $wallTimeLimit,
            'memory_limit' => $memoryLimit,
        ], $testCases);

        $results = $this->client->runBatch($submissions);

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

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
    public function judge(string $sourceCode, ?string $language, array $testCases, int $timeLimitMs, int $memoryLimitKb): array
    {
        if ($testCases === []) {
            return ['verdict' => VerdictStatus::SystemError, 'isAccepted' => false, 'details' => []];
        }

        $languageId = config("judge0.languages.{$language}");

        if ($languageId === null || trim($sourceCode) === '') {
            return ['verdict' => VerdictStatus::CompileError, 'isAccepted' => false, 'details' => []];
        }

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

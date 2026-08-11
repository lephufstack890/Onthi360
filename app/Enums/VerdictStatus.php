<?php

namespace App\Enums;

enum VerdictStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Judging = 'judging';
    case Accepted = 'accepted';
    case WrongAnswer = 'wrong_answer';
    case TimeLimitExceeded = 'time_limit_exceeded';
    case MemoryLimitExceeded = 'memory_limit_exceeded';
    case RuntimeError = 'runtime_error';
    case CompileError = 'compile_error';
    case SystemError = 'system_error';

    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::Queued, self::Judging], true);
    }
}

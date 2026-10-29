<?php

namespace App\Enums;

enum AnswerSheetQuestionType: string
{
    case SingleChoice = 'single_choice';

    case TrueFalse = 'true_false';

    case TrueFalseGroup = 'true_false_group';

    case ShortAnswer = 'short_answer';

    case MultiPart = 'multi_part';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Trắc nghiệm (A/B/C/D)',
            self::TrueFalse => 'Đúng/Sai',
            self::TrueFalseGroup => 'Đúng/Sai 4 ý',
            self::ShortAnswer => 'Trả lời ngắn (số)',
            self::MultiPart => 'Câu nhiều ý',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SingleChoice => '🔤',
            self::TrueFalse => '⚖️',
            self::TrueFalseGroup => '✅',
            self::ShortAnswer => '✏️',
            self::MultiPart => '🧩',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SingleChoice => 'bg-sky-50 text-sky-600 border-sky-200',
            self::TrueFalse => 'bg-amber-50 text-amber-600 border-amber-200',
            self::TrueFalseGroup => 'bg-emerald-50 text-emerald-600 border-emerald-200',
            self::ShortAnswer => 'bg-violet-50 text-violet-600 border-violet-200',
            self::MultiPart => 'bg-rose-50 text-rose-600 border-rose-200',
        };
    }

    public static function simpleCases(): array
    {
        return [self::SingleChoice, self::TrueFalse, self::ShortAnswer];
    }
}

<?php

namespace App\Enums;

enum QuestionType: string
{
    case Coding = 'coding';
    case Mcq = 'mcq';
    case FillBlank = 'fill_blank';
    case Composite = 'composite';

    public function label(): string
    {
        return match ($this) {
            self::Coding => 'Lập trình (OJ)',
            self::Mcq => 'Trắc nghiệm',
            self::FillBlank => 'Điền khuyết',
            self::Composite => 'Nhiều phần (composite)',
        };
    }
}

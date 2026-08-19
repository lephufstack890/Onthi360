<?php

namespace App\Enums;

enum AssessmentType: string
{
    case Practice = 'practice';
    case Assignment = 'assignment';
    case Exam = 'exam';
    case CompetitionPaper = 'competition_paper';

    public function label(): string
    {
        return match ($this) {
            self::Practice => 'Tự luyện',
            self::Assignment => 'Bài tập',
            self::Exam => 'Đề thi',
            self::CompetitionPaper => 'Đề thi cuộc thi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Practice => '📝',
            self::Assignment => '📗',
            self::Exam => '🧾',
            self::CompetitionPaper => '🏆',
        };
    }
}

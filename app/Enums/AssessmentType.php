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
            // SỬA 18/9 — khách gọi loại này là "Luyện tập" ở mọi chỗ (ô Loại bên admin và bên
            // giáo viên đều ghi "Luyện tập"), nhãn enum để "Tự luyện" là cùng một thứ mà hai
            // tên, dễ tưởng là hai loại khác nhau. Giá trị lưu trong CSDL ('practice') KHÔNG đổi.
            self::Practice => 'Luyện tập',
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

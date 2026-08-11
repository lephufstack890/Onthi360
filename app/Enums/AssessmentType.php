<?php

namespace App\Enums;

enum AssessmentType: string
{
    case Practice = 'practice';
    case Assignment = 'assignment';
    case Exam = 'exam';
    case CompetitionPaper = 'competition_paper';
}

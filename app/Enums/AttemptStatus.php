<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Grading = 'grading';
    case Graded = 'graded';
    case Expired = 'expired';
}

<?php

namespace App\Enums;

enum QuestionType: string
{
    case Coding = 'coding';
    case Mcq = 'mcq';
    case FillBlank = 'fill_blank';
}

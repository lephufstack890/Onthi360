<?php

namespace App\Enums;

enum ReviewerRole: string
{
    case Student = 'student';
    case Parent = 'parent';
    case Teacher = 'teacher';
}

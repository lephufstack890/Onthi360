<?php

namespace App\Enums;

enum AttemptSource: string
{
    case PublicPractice = 'public';
    case Personal = 'personal';
    case ClassRoom = 'class_room';
    case Assignment = 'assignment';
}

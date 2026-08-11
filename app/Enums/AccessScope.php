<?php

namespace App\Enums;

enum AccessScope: string
{
    case PersonalLearning = 'personal_learning';
    case TeacherTeaching = 'teacher_teaching';
}

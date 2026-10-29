<?php

namespace App\Enums;

enum OwnerType: string
{
    case Shared = 'shared';
    case Teacher = 'teacher';
    case Partner = 'partner';
}

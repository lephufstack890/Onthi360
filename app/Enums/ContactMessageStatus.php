<?php

namespace App\Enums;

enum ContactMessageStatus: string
{
    case New = 'new';
    case Resolved = 'resolved';
}

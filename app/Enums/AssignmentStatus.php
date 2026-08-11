<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';
}

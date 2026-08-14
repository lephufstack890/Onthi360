<?php

namespace App\Enums;

enum TokenTopupStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

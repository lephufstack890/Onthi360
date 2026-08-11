<?php

namespace App\Enums;

enum ClassMaterialStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Hidden = 'hidden';
    case SuspendedExpired = 'suspended_expired';
    case Removed = 'removed';
}

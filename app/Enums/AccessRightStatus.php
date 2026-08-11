<?php

namespace App\Enums;

enum AccessRightStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}

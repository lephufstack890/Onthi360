<?php

namespace App\Enums;

enum ParentLinkStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Revoked = 'revoked';
}

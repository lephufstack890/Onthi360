<?php

namespace App\Enums;

enum ActivationCodeStatus: string
{
    case Unused = 'unused';
    case Activated = 'activated';
    case Revoked = 'revoked';
}

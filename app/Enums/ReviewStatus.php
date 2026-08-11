<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InModeration = 'in_moderation';
    case Published = 'published';
    case NeedsRevision = 'needs_revision';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
}

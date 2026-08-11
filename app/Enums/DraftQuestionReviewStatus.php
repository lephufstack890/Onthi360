<?php

namespace App\Enums;

enum DraftQuestionReviewStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Merged = 'merged';
    case Discarded = 'discarded';
}

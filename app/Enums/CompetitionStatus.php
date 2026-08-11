<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case PendingPublish = 'pending_publish';
    case Published = 'published';
    case Archived = 'archived';
}

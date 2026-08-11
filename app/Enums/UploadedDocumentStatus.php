<?php

namespace App\Enums;

enum UploadedDocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Scanning = 'scanning';
    case QueuedOcr = 'queued_ocr';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
    case Promoted = 'promoted';
}

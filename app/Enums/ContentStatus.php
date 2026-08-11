<?php

namespace App\Enums;

/**
 * Trạng thái xuất bản dùng chung cho Product, Question, Assessment.
 * Dùng string column (không dùng MySQL ENUM) để thêm trạng thái mới
 * chỉ cần sửa enum PHP, không cần ALTER TABLE.
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Archived = 'archived';
}

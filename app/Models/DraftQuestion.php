<?php

namespace App\Models;

use App\Enums\DraftQuestionReviewStatus;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftQuestion extends Model
{
    protected $fillable = [
        'uploaded_document_id', 'order', 'type_guess', 'raw_text', 'structured_draft',
        'confidence', 'review_status', 'reviewed_by', 'promoted_question_id',
    ];

    protected $casts = [
        'type_guess' => QuestionType::class,
        'structured_draft' => 'array',
        'review_status' => DraftQuestionReviewStatus::class,
    ];

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function promotedQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'promoted_question_id');
    }

    /** Vùng nhận dạng kém phải được gắn cờ, không giả định đúng (6.4). */
    public function needsManualReview(): bool
    {
        return $this->confidence !== 'high' || $this->review_status === DraftQuestionReviewStatus::Pending;
    }
}

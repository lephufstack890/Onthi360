<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 bài lập trình con nằm TRONG 1 đề PDF (VD: Câu 5 của đề là 1 bài code) — Assessment
 * content_mode=pdf_answer_sheet có thể vừa có phần trắc nghiệm (AssessmentAnswerKey) vừa
 * có phần lập trình (bảng này) trong CÙNG 1 đề (16/8 mục 6 "Giao diện làm bài": "Một đề có
 * thể vừa có phần trắc nghiệm vừa có phần lập trình").
 */
class AssessmentCodingItem extends Model
{
    protected $fillable = [
        'assessment_id', 'code', 'title', 'pdf_page', 'allowed_languages',
        'time_limit_ms', 'memory_limit_kb', 'points',
    ];

    protected $casts = [
        'allowed_languages' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(AssessmentCodingTestCase::class, 'coding_item_id')->orderBy('order');
    }

    public function hiddenTestCases(): HasMany
    {
        return $this->testCases()->where('is_sample', false);
    }

    public function sampleTestCases(): HasMany
    {
        return $this->testCases()->where('is_sample', true);
    }
}

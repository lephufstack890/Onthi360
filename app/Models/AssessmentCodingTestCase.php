<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 cặp input/output của 1 bài lập trình con (App\Models\AssessmentCodingItem) — sinh ra khi
 * Admin tải gói ZIP test case lên, hệ thống tách từng cặp file rồi lưu đường dẫn ở đây.
 */
class AssessmentCodingTestCase extends Model
{
    protected $fillable = ['coding_item_id', 'order', 'input_path', 'expected_output_path', 'is_sample'];

    protected $casts = [
        'is_sample' => 'boolean',
    ];

    public function codingItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentCodingItem::class, 'coding_item_id');
    }
}

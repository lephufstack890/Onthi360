<?php

namespace App\Models;

use App\Enums\TokenTopupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Yêu cầu nạp token qua chuyển khoản ngân hàng (note họp 13/8, mục 7-8). */
class TokenTopup extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'transfer_code', 'status',
        'reviewed_by', 'reviewed_at', 'reject_reason',
    ];

    protected $casts = [
        'status' => TokenTopupStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

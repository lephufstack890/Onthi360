<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationCode extends Model
{
    use HasFactory, Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'code', 'order_item_id', 'product_id', 'scope', 'status',
        'activated_by', 'activated_at', 'validity_months',
    ];

    protected $casts = [
        'scope' => AccessScope::class,
        'status' => ActivationCodeStatus::class,
        'activated_at' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function isUsable(): bool
    {
        return $this->status === ActivationCodeStatus::Unused;
    }
}

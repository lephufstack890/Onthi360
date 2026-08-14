<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cấu hình hệ thống dạng key-value (3.1, 18.8) — thay cho hằng số hard-code
 * trong code, để Super Admin điều chỉnh không cần release (18.8).
 */
class SystemSetting extends Model
{
    protected $fillable = [
        'key', 'value', 'type', 'label', 'description', 'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function intValue(?int $default = null): ?int
    {
        return $this->value !== null && $this->value !== '' ? (int) $this->value : $default;
    }

    public function stringValue(?string $default = null): ?string
    {
        return $this->value !== null && $this->value !== '' ? (string) $this->value : $default;
    }
}

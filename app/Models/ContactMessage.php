<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'message', 'status', 'handled_by', 'handled_at'];

    protected $casts = [
        'status' => ContactMessageStatus::class,
        'handled_at' => 'datetime',
    ];

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isResolved(): bool
    {
        return $this->status === ContactMessageStatus::Resolved;
    }
}

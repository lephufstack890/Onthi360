<?php

namespace App\Models;

use App\Enums\ParentLinkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentLink extends Model
{
    protected $fillable = [
        'parent_user_id', 'student_user_id', 'status', 'verification_method', 'verified_at',
    ];

    protected $casts = [
        'status' => ParentLinkStatus::class,
        'verified_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function isVerified(): bool
    {
        return $this->status === ParentLinkStatus::Verified;
    }
}

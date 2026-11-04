<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPairingCode extends Model
{
    protected $fillable = ['code', 'parent_user_id', 'student_user_id', 'expires_at', 'used_at', 'device_id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}

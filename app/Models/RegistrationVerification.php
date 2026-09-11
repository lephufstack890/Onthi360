<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bản ghi đăng ký TẠM, tồn tại giữa bước 1 (nhập thông tin) và bước 3 (chọn vai trò) của
 * luồng đăng ký 3 bước. Xem migration create_registration_verifications_table để biết vì
 * sao không tạo thẳng User ngay từ bước 1.
 */
class RegistrationVerification extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'password', 'code_hash',
        'expires_at', 'attempts', 'verified_at', 'claim_token',
    ];

    protected $hidden = ['password', 'code_hash', 'claim_token'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}

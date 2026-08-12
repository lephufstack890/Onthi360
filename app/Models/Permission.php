<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission chi tiết theo action, gắn vào Role (không gắn thẳng vào User) —
 * đúng mô hình RBAC chuẩn: đổi quyền của cả một role không phải sửa từng user.
 * Xem App\Providers\AppServiceProvider::boot() (Gate::before) để biết cách
 * $user->can('slug') hoạt động, và App\Http\Middleware\EnsureHasPermission
 * để dùng ở route (middleware('permission:xxx')).
 */
class Permission extends Model
{
    protected $fillable = ['slug', 'label', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}

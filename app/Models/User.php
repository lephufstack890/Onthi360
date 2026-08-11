<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'locale', 'avatar_path', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // -- Hồ sơ theo vai trò --------------------------------------------------

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /** Các liên kết mà user này là PHỤ HUYNH của học sinh khác. */
    public function childLinks(): HasMany
    {
        return $this->hasMany(ParentLink::class, 'parent_user_id');
    }

    /** Các liên kết mà user này là HỌC SINH được phụ huynh nào đó liên kết tới. */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentLink::class, 'student_user_id');
    }

    // -- Lớp / khóa -----------------------------------------------------------

    /** Lớp mà user này là giáo viên phụ trách hoặc đồng phụ trách (7.2). */
    public function classRoomsTeaching(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'class_teachers')->withPivot('role')->withTimestamps();
    }

    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'student_id');
    }

    // -- Quyền / thương mại -----------------------------------------------------

    public function accessRights(): HasMany
    {
        return $this->hasMany(AccessRight::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    // -- Làm bài / đánh giá -----------------------------------------------------

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function isTeacherApproved(): bool
    {
        return $this->teacherProfile?->isApproved() ?? false;
    }

    // -- Vai trò (RBAC tối giản, tự viết — xem docs/ARCHITECTURE.md) -----------

    /**
     * Một user có thể có NHIỀU role đồng thời (vd: vừa dạy vừa là phụ huynh) —
     * đúng yêu cầu "role switcher" ở 4.3. Danh sách role hợp lệ: xem App\Models\Role.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->relationLoaded('roles')
            ? $this->roles->contains('name', $roleName)
            : $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(string ...$roleNames): bool
    {
        foreach ($roleNames as $name) {
            if ($this->hasRole($name)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
    }
}

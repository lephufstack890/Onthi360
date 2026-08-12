<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const STUDENT = 'student';

    public const PARENT = 'parent';

    public const TEACHER = 'teacher';

    public const EDITOR = 'editor';

    public const ADMIN = 'admin';

    public const SUPER_ADMIN = 'super_admin';

    protected $fillable = ['name', 'label'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->relationLoaded('permissions')
            ? $this->permissions->contains('slug', $slug)
            : $this->permissions()->where('slug', $slug)->exists();
    }
}

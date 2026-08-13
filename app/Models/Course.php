<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = ['title', 'slug', 'description', 'cover_image_path', 'subject', 'grade', 'status', 'created_by'];

    protected $casts = [
        'status' => ContentStatus::class,
    ];

    /**
     * Đọc bởi App\Concerns\Auditable — set trước khi delete() để ghi lý do xóa mềm
     * (10.4: "xóa mềm phải có lý do, người thao tác, thời gian và audit log"), xem
     * App\Services\Admin\CourseService::destroy().
     */
    public static ?string $auditReason = null;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }
}

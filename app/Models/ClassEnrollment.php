<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEnrollment extends Model
{
    /**
     * SỬA 16/9 — 4 trạng thái của một dòng ghi danh. Cột status là string(20) tự do nên thêm
     * 'pending'/'rejected' KHÔNG cần đổi lược đồ; AccessGateService::canAccessClassRoom() chỉ
     * chấp nhận 'active' nên 2 trạng thái mới tự động không vào học được.
     */
    public const STATUS_PENDING = 'pending';   // học sinh đã xin vào, chờ giáo viên duyệt

    public const STATUS_ACTIVE = 'active';     // đang học

    public const STATUS_REJECTED = 'rejected'; // giáo viên từ chối

    public const STATUS_LEFT = 'left';         // đã rời lớp

    protected $fillable = [
        'class_room_id', 'student_id', 'status', 'enrolled_at', 'left_at',
        // SỬA 16/9 — dấu vết duyệt, xem migration add_approval_fields_to_class_enrollments_table.
        'requested_at', 'approved_at', 'approved_by', 'reject_reason',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'left_at' => 'datetime',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Máy chủ đã chạy migration thêm 4 cột dấu vết duyệt chưa.
     *
     * Cùng cách phòng vệ như ClassRoom::supportsDisplayFields(): đã có lần đẩy mã lên VPS mà
     * quên `php artisan migrate`, thế là cả màn quản trị hỏng. Chưa chạy migrate thì luồng duyệt
     * vẫn chạy được bằng riêng cột status (có sẵn từ đầu), chỉ mất phần ghi lại ai duyệt lúc nào.
     * Hỏi lược đồ MỘT LẦN mỗi vòng đời tiến trình rồi nhớ lại.
     */
    public static function supportsApprovalFields(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = \Illuminate\Support\Facades\Schema::hasColumn('class_enrollments', 'requested_at');
        }

        return $has;
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** SỬA 16/9 — giáo viên/quản trị đã bấm duyệt yêu cầu này. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

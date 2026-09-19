<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 19/9 — ĐƠN ĐĂNG KÝ tham gia một cuộc thi: học sinh gửi, admin duyệt, duyệt rồi mới vào
 * được không gian thi. Xem migration create_competition_registrations_table.
 *
 * Chặn THẬT nằm ở App\Services\AttemptService::competitionEntryDecision() — chỗ duy nhất quyết
 * định có cho mở đề cuộc thi hay không. Giao diện chỉ hiển thị lại cùng một luật đó.
 */
class CompetitionRegistration extends Model
{
    /** Đã gửi đơn, đang chờ ban tổ chức duyệt. */
    public const STATUS_PENDING = 'pending';

    /** Đã duyệt — vào được không gian thi. */
    public const STATUS_APPROVED = 'approved';

    /** Bị từ chối — giữ lại dòng kèm lý do, KHÔNG xoá (còn là dấu vết để đối chiếu về sau). */
    public const STATUS_REJECTED = 'rejected';

    /** Học sinh tự rút đơn (chưa có giao diện, để sẵn cho sau này). */
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'competition_id', 'student_id', 'status',
        'requested_at', 'approved_at', 'approved_by', 'reject_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Máy chủ đã chạy migration tạo bảng này chưa.
     *
     * Cùng cách phòng vệ với ClassEnrollment::supportsApprovalFields(): đã có lần đẩy mã lên VPS
     * mà quên `php artisan migrate`, cả màn quản trị hỏng. Chưa có bảng thì luồng cuộc thi chạy
     * NHƯ CŨ (vào thẳng khi đang trong khung giờ) thay vì vỡ trang — xem
     * AttemptService::competitionEntryDecision() và Public\CompetitionService.
     * Hỏi lược đồ MỘT LẦN mỗi vòng đời tiến trình rồi nhớ lại.
     */
    public static function supported(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasTable('competition_registrations');
        }

        return $has;
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}

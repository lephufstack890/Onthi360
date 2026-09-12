<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use App\Enums\SupportTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một yêu cầu hỗ trợ gửi từ form ở trang Thông tin công khai.
 *
 * CHỈ QUẢN TRỊ VIÊN ĐỌC ĐƯỢC: mọi đường dẫn xem/xử lý nằm trong nhóm
 * Route::middleware(['role:admin,super_admin']) ở routes/web.php, và mục menu "Yêu cầu hỗ trợ"
 * bị ẩn với tài khoản chỉ có vai trò biên tập. Không có bất kỳ màn công khai nào đọc bảng này.
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'ticket_code', 'name', 'email', 'phone', 'user_id', 'message', 'topic', 'ip_hash',
        'status', 'handled_by', 'handled_at', 'admin_note',
    ];

    protected $casts = [
        'status' => ContactMessageStatus::class,
        'topic' => SupportTopic::class,
        'handled_at' => 'datetime',
    ];

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Người gửi lúc đó đang đăng nhập thì có; khách vãng lai thì null. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isResolved(): bool
    {
        return $this->status === ContactMessageStatus::Resolved;
    }

    /** Còn phải làm gì đó — dùng để đếm số việc tồn trên menu. */
    public function isOpen(): bool
    {
        return in_array($this->status, [ContactMessageStatus::New, ContactMessageStatus::InProgress], true);
    }

    public function topicLabel(): string
    {
        return $this->topic?->label() ?? 'Chưa phân loại';
    }
}

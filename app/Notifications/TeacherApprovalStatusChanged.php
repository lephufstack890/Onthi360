<?php

namespace App\Notifications;

use App\Enums\TeacherApprovalStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Gửi khi Admin duyệt/từ chối/tạm dừng hồ sơ giáo viên (3.3) — kích hoạt từ
 * App\Services\Admin\TeacherApprovalService::approve()/reject()/suspend(). Dùng kênh
 * 'database' mặc định của Illuminate Notifications (App\Models\User đã có trait
 * Notifiable) thay vì bảng notifications tự chế, để tránh xung đột schema.
 */
class TeacherApprovalStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TeacherApprovalStatus $status,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        [$icon, $tone, $title, $text] = match ($this->status) {
            TeacherApprovalStatus::Approved => [
                '✅', 'success', 'Hồ sơ giáo viên đã được duyệt',
                'Hồ sơ giáo viên của bạn đã được duyệt — bạn có thể tạo lớp và giao đề ngay bây giờ.',
            ],
            TeacherApprovalStatus::Rejected => [
                '❌', 'danger', 'Hồ sơ giáo viên bị từ chối',
                'Hồ sơ giáo viên của bạn bị từ chối'.($this->reason ? ": {$this->reason}" : '.'),
            ],
            TeacherApprovalStatus::Suspended => [
                '⏸️', 'warning', 'Tài khoản giáo viên tạm dừng',
                'Quyền giáo viên của bạn đã bị tạm dừng'.($this->reason ? ": {$this->reason}" : '.'),
            ],
            default => ['🔔', 'neutral', 'Cập nhật hồ sơ giáo viên', 'Trạng thái hồ sơ giáo viên của bạn đã thay đổi.'],
        };

        return [
            'icon' => $icon,
            'tone' => $tone,
            'title' => $title,
            'text' => $text,
            'url' => route('dashboard'),
        ];
    }
}

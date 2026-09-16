<?php

namespace App\Notifications;

use App\Models\ClassRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 16/9 — báo HỌC SINH bị giáo viên gỡ khỏi lớp.
 *
 * Không có thông báo này thì em đang học tự dưng mở lớp ra bị chặn, không hiểu vì sao và sẽ đi
 * báo lỗi. Url trỏ về danh sách lớp công khai chứ KHÔNG trỏ vào lớp vừa bị gỡ — không còn là
 * thành viên thì vào là 403 (xem App\Services\AccessGateService::canAccessClassRoom()).
 */
class ClassMembershipRemoved extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ClassRoom $classRoom,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon' => '🚪',
            'tone' => 'warning',
            'title' => 'Bạn đã được gỡ khỏi lớp',
            'text' => 'Bạn không còn trong lớp '.$this->classRoom->name.' ('.$this->classRoom->code.')'
                .($this->reason ? ': '.$this->reason : '.')
                .' Cần học lại thì đăng ký lớp ở trang Lớp học.',
            'url' => route('courses.index'),
        ];
    }
}

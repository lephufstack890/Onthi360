<?php

namespace App\Notifications;

use App\Models\ClassAnnouncement;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * SỬA 12/10 — thông báo lớp do giáo viên đăng (mục "Thông báo" khu Giáo viên). Gửi tới học sinh
 * trong lớp và, nếu giáo viên chọn, phụ huynh đã liên kết. Dùng kênh database như các thông báo khác
 * nên hiện ngay ở chuông / trang Thông báo của người nhận.
 */
class ClassAnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ClassAnnouncement $announcement,
        private readonly ClassRoom $classRoom,
        private readonly User $teacher,
        private readonly bool $forParent = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = trim((string) $this->announcement->title) !== '' ? $this->announcement->title : 'Thông báo từ lớp '.$this->classRoom->name;

        return [
            'icon' => '📣',
            'tone' => 'info',
            'title' => $title,
            'text' => ($this->forParent ? 'Thông báo cho phụ huynh lớp ' : 'Lớp ').$this->classRoom->name.' · '.$this->teacher->name.': '
                .Str::limit(trim((string) $this->announcement->body), 160),
            'url' => null,
        ];
    }
}

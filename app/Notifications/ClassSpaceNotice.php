<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 6/11 — thông báo ngắn trong không gian lớp: được thêm vào lớp làm giáo viên, nhắc nộp bài, mở khoá bước tiến trình.
 * Kênh database (chuông thông báo), cùng khuôn dữ liệu {icon, tone, title, text, url} với các thông báo khác.
 */
class ClassSpaceNotice extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $icon,
        private readonly string $tone,
        private readonly string $title,
        private readonly string $text,
        private readonly ?string $url = null,
        private readonly ?string $from = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_filter([
            'icon' => $this->icon,
            'tone' => $this->tone,
            'title' => $this->title,
            'text' => $this->text,
            'url' => $this->url,
            'from' => $this->from,
        ], fn ($v) => $v !== null);
    }
}

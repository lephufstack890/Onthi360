<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 4/11 — báo PHỤ HUYNH các việc trên máy tính của con (Chocon Desktop): con xin thêm giờ, gỡ phần mềm,
 * nhập sai PIN, chỉnh đồng hồ... Bấm vào mở trang "Máy tính của con".
 */
class ChoconAlert extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $icon,
        private readonly string $tone,
        private readonly string $title,
        private readonly string $text,
        private readonly int $studentId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon,
            'tone' => $this->tone,
            'title' => $this->title,
            'text' => $this->text,
            'url' => route('parent.devices.index', ['child' => $this->studentId]),
        ];
    }
}

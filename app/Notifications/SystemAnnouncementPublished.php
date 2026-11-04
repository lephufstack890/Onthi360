<?php

namespace App\Notifications;

use App\Models\SystemAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * SỬA 4/11 — thông báo chung do Quản trị đăng (App\Models\SystemAnnouncement). Kênh database như mọi thông báo khác nên
 * hiện ngay ở chuông / trang Thông báo của người nhận; bấm vào mở trang chi tiết đọc đủ nội dung (notifications.show).
 *
 * Gửi số lượng lớn (cả hệ thống) thì App\Services\Admin\SystemAnnouncementService ghi thẳng từng lô vào bảng notifications
 * bằng đúng payload() dưới đây — cùng cấu trúc như kênh database của Laravel tự ghi, chỉ nhanh hơn.
 */
class SystemAnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly SystemAnnouncement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return self::payload($this->announcement);
    }

    /** @return array{icon: string, tone: string, title: string, text: string, url: null, ref: string, from: string, level: string} */
    public static function payload(SystemAnnouncement $a): array
    {
        $important = $a->level === 'important';
        $title = trim((string) $a->title) !== '' ? (string) $a->title : 'Thông báo từ Ôn Thi 360';

        return [
            'icon' => $important ? '📢' : '📣',
            'tone' => $important ? 'warning' : 'info',
            'title' => ($important ? '[Quan trọng] ' : '').$title,
            'text' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $a->body)), 160),
            'url' => null,
            'ref' => $a->refKey(),
            'from' => 'Ban quản trị Ôn Thi 360',
            'level' => $important ? 'important' : 'normal',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Competition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 19/9 — báo HỌC SINH kết quả duyệt đơn đăng ký cuộc thi. Chép khuôn ClassJoinDecided
 * (SỬA 16/9) để hai luồng duyệt trong hệ thống báo tin giống nhau.
 *
 * Lưu ý về 'url': duyệt rồi thì trỏ thẳng vào KHÔNG GIAN THI (đã vào được). BỊ TỪ CHỐI thì
 * KHÔNG trỏ vào đó — chưa được duyệt nên vào là 403; trỏ về trang cuộc thi công khai để học
 * sinh đọc lại thể lệ hoặc đăng ký cuộc thi khác.
 */
class CompetitionJoinDecided extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Competition $competition,
        private readonly bool $approved,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->approved) {
            return [
                'icon' => '🏆',
                'tone' => 'success',
                'title' => 'Đơn tham gia cuộc thi đã được duyệt',
                'text' => 'Bạn đã được duyệt tham gia "'.$this->competition->title.'" — vào không gian thi để xem các vòng và giờ thi.',
                'url' => route('student.competitions.room', ['competition' => $this->competition->id]),
            ];
        }

        return [
            'icon' => '⛔',
            'tone' => 'warning',
            'title' => 'Đơn tham gia cuộc thi chưa được duyệt',
            'text' => 'Đơn tham gia "'.$this->competition->title.'" chưa được duyệt.'
                .($this->reason ? ' Lý do: '.$this->reason : ''),
            'url' => route('competitions.index'),
        ];
    }
}

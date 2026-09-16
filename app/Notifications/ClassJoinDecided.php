<?php

namespace App\Notifications;

use App\Models\ClassRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 16/9 — báo HỌC SINH kết quả duyệt yêu cầu vào lớp.
 *
 * Lưu ý về 'url': duyệt rồi thì trỏ vào chính trang lớp (đã vào được, và
 * Student\ClassRoomService::notificationsForClass() lọc theo url nên thông báo này còn hiện
 * đúng trong tab Thông báo của lớp đó). BỊ TỪ CHỐI thì KHÔNG trỏ vào trang lớp — học sinh
 * không phải thành viên nên vào là 403; trỏ về danh sách lớp công khai để chọn lớp khác.
 */
class ClassJoinDecided extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ClassRoom $classRoom,
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
                'icon' => '✅',
                'tone' => 'success',
                'title' => 'Yêu cầu vào lớp đã được duyệt',
                'text' => 'Bạn đã được nhận vào lớp '.$this->classRoom->name.' ('.$this->classRoom->code.') — vào học ngay thôi!',
                'url' => route('student.classes.show', ['class' => $this->classRoom->id]),
            ];
        }

        return [
            'icon' => '❌',
            'tone' => 'danger',
            'title' => 'Yêu cầu vào lớp chưa được duyệt',
            'text' => 'Yêu cầu vào lớp '.$this->classRoom->name.' chưa được duyệt'
                .($this->reason ? ': '.$this->reason : '.'),
            'url' => route('courses.index'),
        ];
    }
}

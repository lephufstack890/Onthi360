<?php

namespace App\Notifications;

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 16/9 — báo GIÁO VIÊN của lớp có học sinh vừa bấm "Đăng ký học" ngoài trang lớp công khai.
 * Không có thông báo này thì yêu cầu nằm im trong bảng, giáo viên không có lý do gì để mở tab
 * Thành viên ra xem, học sinh chờ mãi không được duyệt.
 *
 * Dùng kênh 'database' như App\Notifications\TeacherApprovalStatusChanged (User đã có Notifiable),
 * url trỏ THẲNG vào tab Thành viên của đúng lớp để bấm duyệt ngay.
 */
class ClassJoinRequested extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ClassRoom $classRoom,
        private readonly User $student,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon' => '🙋',
            'tone' => 'info',
            'title' => 'Có yêu cầu vào lớp mới',
            'text' => $this->student->name.' xin vào lớp '.$this->classRoom->name.' ('.$this->classRoom->code.').',
            'url' => route('teacher.classes.show', ['class' => $this->classRoom->id, 'tab' => 'members']),
        ];
    }
}

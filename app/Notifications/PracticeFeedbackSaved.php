<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 12/10 — báo HỌC SINH khi giáo viên nhận xét một bài / đề đã nộp (xem ở Bài & đề được giao > Chi tiết).
 */
class PracticeFeedbackSaved extends Notification
{
    use Queueable;

    public function __construct(private readonly User $teacher) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon' => '📝',
            'tone' => 'info',
            'title' => 'Giáo viên đã nhận xét bài của bạn',
            'text' => $this->teacher->name.' vừa để lại nhận xét ở mục Bài & đề được giao.',
            'url' => route('student.assignments.index'),
        ];
    }
}

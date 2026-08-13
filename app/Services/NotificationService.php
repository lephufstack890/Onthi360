<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Dịch vụ thông báo dùng CHUNG mọi vai trò (chuông thông báo global ở
 * resources/views/partials/notifications-bell.blade.php + teacher.notifications.index +
 * student.notifications) — không đặt dưới namespace Teacher/Student vì bell được include
 * ở layouts.app cho mọi role. Dựa trên hệ thống Illuminate Notifications sẵn có
 * (App\Models\User đã có trait Notifiable) qua kênh 'database'.
 */
class NotificationService
{
    /** @return array{items: array, unreadCount: int} */
    public function forUser(User $user, int $limit = 30): array
    {
        $notifications = $user->notifications()->limit($limit)->get();

        return [
            'items' => $notifications->map(fn ($n) => $this->mapNotification($n))->all(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ];
    }

    /** Chuông thông báo global — 5 thông báo mới nhất + số chưa đọc (dùng trong View Composer). */
    public function bellData(User $user): array
    {
        $notifications = $user->notifications()->limit(5)->get();

        return [
            'items' => $notifications->map(fn ($n) => $this->mapNotification($n))->all(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ];
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->where('id', $notificationId)->first();
        $notification?->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    private function mapNotification(DatabaseNotification $n): array
    {
        $data = $n->data;

        return [
            'id' => $n->id,
            'icon' => $data['icon'] ?? '🔔',
            'tone' => $data['tone'] ?? 'neutral',
            'title' => $data['title'] ?? '',
            'text' => $data['text'] ?? ($data['title'] ?? ''),
            'url' => $data['url'] ?? null,
            'read' => $n->read_at !== null,
            'time' => $n->created_at?->diffForHumans(),
        ];
    }
}

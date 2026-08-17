<?php

namespace App\Services\Admin;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;

/**
 * Gom truy vấn/hành động cho admin.contact-messages.index — tin nhắn gửi từ form "Liên hệ"
 * ở trang công khai info.index (PUB-11, 4.1). Gắn chung nhóm điều hướng với "Đánh giá" (không
 * thêm mục nav riêng) vì cả 2 đều là nội dung công khai gửi tới cần admin xem/xử lý — sidebar
 * đã cố định đúng 12 mục theo BA spec (resources/views/partials/sidebar-admin.blade.php).
 */
class ContactMessageService
{
    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactMessages,
        private readonly ReviewRepositoryInterface $reviews,
    ) {}

    /** @return array{tabs: array, messages: array} */
    public function indexData(): array
    {
        $tabs = [
            ['label' => 'Đánh giá', 'href' => route('admin.reviews.index'), 'active' => false, 'count' => $this->reviews->countPendingModeration()],
            ['label' => 'Tin nhắn liên hệ', 'href' => route('admin.contact-messages.index'), 'active' => true, 'count' => $this->contactMessages->countNew()],
        ];

        $messages = $this->contactMessages->recent(100)->map(fn (ContactMessage $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'message' => $m->message,
            'resolved' => $m->isResolved(),
            'handled_by' => $m->handledBy->name ?? null,
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
        ])->all();

        return ['tabs' => $tabs, 'messages' => $messages];
    }

    /** admin.contact-messages.resolve — đánh dấu đã xử lý, ghi nhận ai xử lý và lúc nào. */
    public function resolve(User $admin, ContactMessage $message): ContactMessage
    {
        $message->update([
            'status' => ContactMessageStatus::Resolved,
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);

        return $message;
    }
}

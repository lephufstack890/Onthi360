<?php

namespace App\Services\Admin;

use App\Enums\ContactMessageStatus;
use App\Enums\SupportTopic;
use App\Models\ContactMessage;
use App\Models\User;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;

/**
 * Khu "Yêu cầu hỗ trợ" của quản trị — nơi duy nhất đọc được nội dung người dùng gửi từ form
 * ở trang Thông tin công khai.
 *
 * CHỈ QUẢN TRỊ VIÊN: tất cả đường dẫn nằm trong nhóm role:admin,super_admin ở routes/web.php.
 * Tài khoản chỉ có vai trò biên tập (editor) không thấy mục menu và cũng không vào được bằng
 * cách gõ thẳng địa chỉ.
 *
 * SỬA 13/9 — trước đây màn này chỉ liệt kê và cho bấm "đã xử lý". Bổ sung: lọc theo trạng thái
 * và loại yêu cầu, tìm kiếm, nhận việc (đang xử lý) để hai người không cùng trả lời một phiếu,
 * đánh dấu rác, và ghi chú nội bộ.
 */
class ContactMessageService
{
    /** Số phiếu nạp ra một lần — thực tế mỗi ngày vài chục phiếu nên không cần phân trang. */
    private const LIST_LIMIT = 200;

    public function __construct(private readonly ContactMessageRepositoryInterface $contactMessages) {}

    /**
     * @param  array{status?: string|null, topic?: string|null, q?: string|null}  $filters
     */
    public function indexData(array $filters = []): array
    {
        $status = $this->validValue($filters['status'] ?? null, array_column(ContactMessageStatus::cases(), 'value'));
        $topic = $this->validValue($filters['topic'] ?? null, array_keys(SupportTopic::options()));
        $keyword = trim((string) ($filters['q'] ?? '')) ?: null;

        $counts = $this->contactMessages->countsByStatus();
        $total = array_sum($counts);

        $rows = $this->contactMessages->search(
            // Chưa chạy migrate thì các cột mới chưa có — lọc theo loại sẽ làm câu truy vấn
            // hỏng, nên bỏ qua bộ lọc đó cho tới khi lược đồ đã đủ.
            ['status' => $status, 'topic' => $this->schemaReady() ? $topic : null, 'q' => $keyword],
            self::LIST_LIMIT,
        );

        return [
            'schemaReady' => $this->schemaReady(),
            'filters' => ['status' => $status, 'topic' => $topic, 'q' => $keyword],
            'statusTabs' => $this->statusTabs($status, $counts, $total),
            'topicOptions' => SupportTopic::options(),
            'stats' => [
                'new' => $counts[ContactMessageStatus::New->value] ?? 0,
                'inProgress' => $counts[ContactMessageStatus::InProgress->value] ?? 0,
                'resolved' => $counts[ContactMessageStatus::Resolved->value] ?? 0,
                'today' => $this->contactMessages->countToday(),
            ],
            'total' => $total,
            'messages' => $rows->map(fn (ContactMessage $m) => $this->row($m))->all(),
        ];
    }

    /** Đổi trạng thái phiếu; ghi lại ai đụng vào và lúc nào. */
    public function changeStatus(User $admin, ContactMessage $message, ContactMessageStatus $status): ContactMessage
    {
        $message->update([
            'status' => $status,
            // Trả phiếu về "Mới" tức là nhả việc ra cho người khác nhận — xoá luôn dấu người xử lý.
            'handled_by' => $status === ContactMessageStatus::New ? null : $admin->id,
            'handled_at' => $status === ContactMessageStatus::New ? null : now(),
        ]);

        return $message;
    }

    /** Ghi chú nội bộ — chỉ quản trị viên đọc, không bao giờ gửi cho người gửi phiếu. */
    public function saveNote(User $admin, ContactMessage $message, ?string $note): ContactMessage
    {
        $message->update(['admin_note' => $note !== null && trim($note) !== '' ? trim($note) : null]);

        return $message;
    }

    /**
     * Bảng đã có các cột của bản 13/9 chưa (ticket_code, topic, phone, admin_note...).
     *
     * Lúc triển khai, mã nguồn lên máy chủ trước còn `php artisan migrate` chạy sau. Trong
     * khoảng giữa đó màn này vẫn phải xem được — chỉ tạm khoá phần lọc theo loại và ghi chú,
     * kèm một dòng nhắc, thay vì đổ lỗi 500 vào mặt quản trị viên.
     */
    public function schemaReady(): bool
    {
        static $ready = null;

        if ($ready === null) {
            $ready = \Illuminate\Support\Facades\Schema::hasColumn('contact_messages', 'admin_note');
        }

        return $ready;
    }

    /** Số phiếu chưa ai nhận — hiện thành viên số cạnh mục menu. */
    public function openCount(): int
    {
        return $this->contactMessages->countNew();
    }

    private function row(ContactMessage $m): array
    {
        $status = $m->status ?? ContactMessageStatus::New;

        return [
            'id' => $m->id,
            'ticket' => $m->ticket_code,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone,
            // Người gửi lúc đó đang đăng nhập thì cho đường dẫn sang hồ sơ để tra cứu nhanh.
            'accountName' => $m->user?->name,
            'accountUrl' => $m->user_id ? route('admin.users.show', $m->user_id) : null,
            'message' => $m->message,
            'topic' => $m->topicLabel(),
            'topicTone' => $m->topic?->tone() ?? 'neutral',
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'statusTone' => $status->tone(),
            'statusIcon' => $status->icon(),
            'resolved' => $m->isResolved(),
            'open' => $m->isOpen(),
            'note' => $m->admin_note,
            'handledBy' => $m->handledBy->name ?? null,
            'handledAt' => $m->handled_at?->format('d/m/Y H:i'),
            'createdAt' => $m->created_at?->format('d/m/Y H:i'),
            'createdAgo' => $m->created_at?->diffForHumans(),
            // Nút "Trả lời qua email" mở sẵn thư có tiêu đề kèm mã phiếu.
            'mailto' => 'mailto:'.$m->email.'?subject='.rawurlencode('[Ôn Thi 360] Phản hồi yêu cầu hỗ trợ '.$m->ticket_code),
        ];
    }

    private function statusTabs(?string $active, array $counts, int $total): array
    {
        $tabs = [[
            'label' => 'Tất cả',
            'href' => route('admin.contact-messages.index'),
            'active' => $active === null,
            'count' => $total,
        ]];

        foreach (ContactMessageStatus::cases() as $case) {
            $tabs[] = [
                'label' => $case->label(),
                'href' => route('admin.contact-messages.index', ['status' => $case->value]),
                'active' => $active === $case->value,
                'count' => $counts[$case->value] ?? 0,
            ];
        }

        return $tabs;
    }

    /** Giá trị lọc gửi lên từ thanh địa chỉ — không nằm trong danh sách cho phép thì bỏ qua. */
    private function validValue(?string $value, array $allowed): ?string
    {
        return $value !== null && in_array($value, $allowed, true) ? $value : null;
    }
}

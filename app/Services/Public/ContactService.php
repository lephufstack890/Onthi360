<?php

namespace App\Services\Public;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;

/**
 * Ghi nhận yêu cầu hỗ trợ gửi từ trang Thông tin công khai (info.contact.store).
 *
 * Ai cũng gửi được, kể cả khách chưa đăng nhập. Kiểm tra dữ liệu nhập nằm ở Controller theo
 * đúng quy ước của dự án; ở đây chỉ lo phần nghiệp vụ: cấp mã phiếu, gắn tài khoản (nếu có)
 * và băm địa chỉ IP.
 */
class ContactService
{
    public function __construct(private readonly ContactMessageRepositoryInterface $contactMessages) {}

    /**
     * @param  array{name: string, email: string, message: string, topic?: string|null, phone?: string|null}  $data
     */
    public function store(array $data, ?User $sender = null, ?string $ip = null): ContactMessage
    {
        $message = $this->contactMessages->create($this->onlyExistingColumns([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
            'topic' => $data['topic'] ?? null,
            'user_id' => $sender?->id,
            'ip_hash' => $this->hashIp($ip),
            'status' => ContactMessageStatus::New->value,
        ]));

        /*
         * Mã phiếu cấp SAU khi ghi để lấy được id — nhờ vậy mã luôn duy nhất mà không cần
         * vòng lặp sinh ngẫu nhiên rồi kiểm tra trùng. Dạng HT2609-00042: nhìn là biết phiếu
         * tháng nào, đọc qua điện thoại cũng không nhầm.
         */
        if ($this->hasColumn('ticket_code')) {
            $message->forceFill([
                'ticket_code' => 'HT'.$message->created_at->format('ym').'-'.str_pad((string) $message->id, 5, '0', STR_PAD_LEFT),
            ])->save();
        }

        return $message;
    }

    /**
     * Chặn lỗi lúc TRIỂN KHAI: mã nguồn mới lên máy chủ trước, `php artisan migrate` chạy sau.
     * Trong khoảng giữa đó bảng contact_messages chưa có các cột mới — nếu cứ ghi thẳng thì
     * MỌI yêu cầu hỗ trợ khách gửi trong khoảng thời gian đó đều báo lỗi 500 và MẤT LUÔN.
     * Thà lưu thiếu vài cột phụ còn hơn đánh rơi lời nhờ giúp đỡ của người dùng.
     *
     * Chạy migrate xong thì nhánh này tự khớp đủ cột, không phải sửa gì thêm.
     */
    private function onlyExistingColumns(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn (string $column) => $this->hasColumn($column),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function hasColumn(string $column): bool
    {
        // Hỏi lược đồ CSDL một lần rồi nhớ lại trong cùng một lần chạy.
        static $columns = null;

        if ($columns === null) {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('contact_messages');
        }

        return in_array($column, $columns, true);
    }

    /**
     * KHÔNG lưu địa chỉ IP thô. Bản băm đủ để nhận ra một nguồn gửi hàng loạt khi cần chặn
     * spam, nhưng không biến bảng yêu cầu hỗ trợ thành nhật ký truy cập của học sinh. Trộn
     * thêm APP_KEY nên rời khỏi hệ thống thì bản băm cũng không truy ngược được.
     */
    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.config('app.key'));
    }
}

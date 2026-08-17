<?php

namespace App\Services\Public;

use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;

/**
 * info.contact.store (PUB-11, 4.1 mục "Liên hệ") — trước đây nút gửi chỉ là
 * <button type="button"> tĩnh, không nối vào đâu cả, không có bảng lưu tin nhắn liên hệ nào
 * trong hệ thống. Ai cũng gửi được, kể cả khách chưa đăng nhập — validate (required/email/
 * max length) thực hiện ở tầng Controller theo đúng quy ước của dự án, service chỉ lưu.
 */
class ContactService
{
    public function __construct(private readonly ContactMessageRepositoryInterface $contactMessages) {}

    public function store(array $data): ContactMessage
    {
        return $this->contactMessages->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
            'status' => 'new',
        ]);
    }
}

<?php

namespace App\Enums;

/**
 * Chủ sở hữu nội dung (product/question). "Shared" là kho chung do
 * Editor/Admin quản lý; "teacher" là kho riêng của giáo viên; "partner"
 * để sẵn cho mô hình đối tác P1 (16.1) mà không cần đổi schema.
 */
enum OwnerType: string
{
    case Shared = 'shared';
    case Teacher = 'teacher';
    case Partner = 'partner';
}

<?php

namespace App\Enums;

enum AttemptSource: string
{
    case PublicPractice = 'public';
    case Personal = 'personal';
    case ClassRoom = 'class_room';
    case Assignment = 'assignment';
    // SỬA 30/10 — lượt LÀM THỬ đề của ban tổ chức kỳ thi / khảo sát (App\Http\Controllers\ContestTestRunController): không thuộc
    // kỳ nào, không tính vào bảng xếp hạng / thống kê công khai.
    case StaffTest = 'staff_test';
}

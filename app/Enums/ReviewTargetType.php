<?php

namespace App\Enums;

enum ReviewTargetType: string
{
    case Material = 'material';
    case ClassRoom = 'class_room';
    // Note họp 13/8, mục 2: "Giáo viên, tài liệu, cuộc thi cần có đánh giá sao của người
    // dùng" — tài liệu (Material) và lớp (ClassRoom) đã có sẵn, bổ sung 2 loại còn thiếu.
    case Teacher = 'teacher';
    case Competition = 'competition';
}

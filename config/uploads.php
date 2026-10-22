<?php

/*
 | SỬA 22/10 (khách: "Chưa nhập được gói đề: Gói ZIP đề must not be greater than 20480 kilobytes") — trần dung lượng gói ZIP
 | (gói đề, gói câu hỏi, bài tập trong tài liệu). Gói có bộ test lớn dễ vượt 20MB. Con số thật nhận được còn bị giới hạn bởi
 | upload_max_filesize / post_max_size của PHP và client_max_body_size của nginx — xem App\Support\UploadLimit::zipKilobytes().
 */
return [
    'zip_max_mb' => (int) env('UPLOAD_ZIP_MAX_MB', 200),
];

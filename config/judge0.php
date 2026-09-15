<?php

/**
 * Cấu hình kết nối tới máy chấm Judge0 tự host (16 mục 1 — "Cầu nối tới judge/runner riêng").
 * Dùng chung cho cả 3 nơi có bài lập trình cần chấm thật trong hệ thống — xem
 * App\Services\CodeJudgingService (nơi DUY NHẤT đọc file này để gọi ra Judge0).
 *
 * base_url LUÔN là http://127.0.0.1:2358, nhưng cổng 2358 đó là gì thì tuỳ nơi chạy:
 *
 *   · TRÊN VPS — onthi360 và Judge0 nằm CHUNG một máy, nên 127.0.0.1:2358 chính là máy chấm.
 *     Judge0 cố ý chỉ nghe ở loopback, không hở ra Internet: nó tồn tại để chạy mã người lạ
 *     gửi lên, mở cổng ra ngoài là tặng không một máy chạy code cho bất kỳ ai dò được cổng.
 *
 *   · TRÊN MÁY LẬP TRÌNH — máy chấm không chạy cục bộ, phải mở đường hầm SSH sang VPS rồi
 *     trỏ vào cổng cục bộ, địa chỉ vẫn y nguyên nên không phải sửa .env:
 *       ssh -N -L 2358:127.0.0.1:2358 root@<IP-VPS>
 *
 * Cách dựng máy chấm trên VPS: xem judge0-laptrinh/README-VPS.md (thư mục dự án riêng của
 * máy chấm, không nằm trong mã nguồn này).
 */
return [

    'base_url' => env('JUDGE0_URL', 'http://127.0.0.1:2358'),

    // Cặp (header, token) bảo vệ API Judge0 — PHẢI khớp AUTHN_HEADER/AUTHN_TOKEN trong
    // judge0.conf trên VPS (lấy bằng lệnh `cat /root/.judge0_authn_token` trên VPS).
    'auth_header' => env('JUDGE0_AUTH_HEADER', 'X-Auth-Token'),
    'auth_token' => env('JUDGE0_AUTH_TOKEN'),

    // Giây — chờ tối đa lúc MỞ kết nối tới Judge0 (không phải chờ chấm xong).
    'connect_timeout' => (int) env('JUDGE0_CONNECT_TIMEOUT', 5),

    // 3 giới hạn dưới đây PHẢI <= đúng MAX_CPU_TIME_LIMIT/MAX_WALL_TIME_LIMIT/MAX_MEMORY_LIMIT
    // đang cấu hình trong judge0.conf trên VPS — CodeJudgingService tự ép (clamp) giới hạn của
    // từng bài/câu hỏi (time_limit_ms, memory_limit_kb/mb) không vượt quá các số này trước khi
    // gửi lên Judge0, tránh bị Judge0 âm thầm chấm sai/không nhất quán khi có câu hỏi giáo viên
    // đặt vượt mức Judge0 cho phép (VD hiện Teacher\QuestionController cho phép tới 2048 MB,
    // trong khi judge0.conf mới cấu hình MAX_MEMORY_LIMIT=1048576 KB = 1024 MB).
    'max_cpu_time_limit' => (int) env('JUDGE0_MAX_CPU_TIME_LIMIT', 60),
    'max_wall_time_limit' => (int) env('JUDGE0_MAX_WALL_TIME_LIMIT', 90),
    'max_memory_limit_kb' => (int) env('JUDGE0_MAX_MEMORY_LIMIT_KB', 1048576),

    // Ánh xạ ngôn ngữ hệ thống ('cpp'/'python' — 2 giá trị DUY NHẤT toàn hệ thống đang cho
    // chọn, xem AssessmentCodingItem::allowed_languages) sang language_id thật của Judge0
    // 1.13.1. Đây là ID CHUẨN của bản Judge0 CE 1.13.x (C++ GCC 9.2.0 / Python 3.8.1) — nếu
    // cài lại Judge0 bằng ảnh khác/bản khác, kiểm tra lại bằng GET {base_url}/languages rồi
    // sửa 2 số này cho khớp trước khi tin kết quả chấm.
    'languages' => [
        'cpp' => (int) env('JUDGE0_LANGUAGE_ID_CPP', 54),
        'python' => (int) env('JUDGE0_LANGUAGE_ID_PYTHON', 71),
    ],

];

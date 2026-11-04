<?php

/*
 * Chocon Desktop — phần mềm quản lý máy tính của con (source C#: OnThi360.Agent).
 *
 * Phát hành bản mới:
 *   1. Build bộ cài trên Windows: scripts\build-installer.ps1 (in ra SHA-256 ở dòng cuối).
 *   2. Chép file .exe vào storage/app/chocon/ trên máy chủ.
 *   3. Sửa .env: CHOCON_VERSION, CHOCON_FILE, CHOCON_SHA256 rồi `php artisan config:clear`.
 * Máy của con tự hỏi API /api/agent/v1/update và tự cập nhật trong vòng vài giờ.
 */
return [
    // Bộ cài mới nhất (nằm trong storage/app/chocon/)
    'version' => env('CHOCON_VERSION', '0.2.0'),
    'file' => env('CHOCON_FILE', 'OnThi360AgentSetup-0.2.0-x64.exe'),
    'sha256' => env('CHOCON_SHA256', ''),
    'mandatory' => (bool) env('CHOCON_MANDATORY', false),
    'notes' => env('CHOCON_NOTES'),

    // Mã ghép máy dùng được trong bao nhiêu phút
    'pairing_code_minutes' => 10,

    // Con mở đề trên web trong vòng bao nhiêu phút gần đây (chưa nộp) thì coi là đang làm bài → bật chế độ tập trung
    'focus_window_minutes' => 120,

    // Máy không gửi tín hiệu quá bao nhiêu phút thì coi là "mất kết nối" (hiện trên trang)
    'offline_after_minutes' => 5,

    // Mất kết nối bất thường quá bao nhiêu phút thì gửi thông báo cho phụ huynh
    'offline_alert_minutes' => 15,

    // Trang làm đề trên onthi360 — mở trang khớp mẫu là phần mềm tự bật chế độ tập trung ngay
    'focus_url_patterns' => [
        'onthi360.vn/student/assessments/*/take*',
        'onthi360.vn/student/competitions/*/lam-bai*',
        'onthi360.vn/cuoc-thi/*/lam-thu/*',
    ],
];

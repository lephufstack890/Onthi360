<?php

/**
 * SỬA 23/9 (khách: "chấm hơn 15 phút chưa xong, câu vẫn Đang chấm") — TRƯỚC ĐÂY DỰ ÁN KHÔNG CÓ
 * FILE NÀY, nên Laravel dùng cấu hình mặc định của framework, trong đó hàng đợi database có
 * retry_after = 90 GIÂY.
 *
 * Đó chính là lý do chấm bài "chạy hoài không xong":
 *
 *   · 1 câu Lập trình 20 test, máy chấm Judge0 chạy tuần tự — thường LÂU HƠN 90 giây.
 *   · Quá 90 giây, hàng đợi coi như việc đó "chết", thả ra cho máy khác nhận.
 *   · Cron chạy mỗi phút lại bật thêm một tiến trình chấm nữa → tiến trình mới nhận đúng câu
 *     mà tiến trình cũ VẪN ĐANG CHẤM. Hai (rồi ba, bốn) tiến trình cùng đẩy một câu sang
 *     Judge0, máy chấm càng nghẽn, càng lâu, càng bị thả ra — vòng lặp tự siết cổ nó.
 *   · Đủ 3 lượt "thử lại" thì việc bị bỏ, câu đứng nguyên "Đang chấm" mãi.
 *
 * QUY TẮC: retry_after PHẢI LỚN HƠN thời gian chạy lâu nhất của một việc
 * (App\Jobs\GradeCodingAnswerJob::$timeout = 300 giây). Để 900 cho rộng tay: việc thật sự
 * treo thì 15 phút sau mới được nhận lại, còn việc đang chạy bình thường KHÔNG BAO GIỜ bị
 * tiến trình khác cướp giữa chừng.
 *
 * Phần còn lại giữ nguyên như mặc định của Laravel, chỉ chép ra đây để sửa được retry_after.
 */
return [

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            // XEM DOCBLOCK ĐẦU FILE — phải > GradeCodingAnswerJob::$timeout (300).
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 900),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 900),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];

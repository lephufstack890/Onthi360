<?php

/**
 * SỬA 8/9 (5) (khách: "ẩn màn Luyện tập bên giáo viên, ẩn loại Tự luyện ở đề/bộ bài bên admin —
 * ẩn thôi, sau tôi kêu mở thì mở, KHÔNG được xoá") — công tắc BẬT/TẮT tính năng.
 *
 * Toàn bộ code của 2 phần này được GIỮ NGUYÊN (route, controller, service, view, dữ liệu cũ
 * trong CSDL đều còn). Muốn mở lại: đổi giá trị dưới đây thành true (hoặc đặt biến môi trường
 * tương ứng trong .env), rồi chạy lại:
 *
 *     php artisan config:clear && php artisan config:cache
 *
 * (bước config:cache là bắt buộc trên server vì cấu hình đang được cache, không đọc lại file này
 * cho tới khi cache được dựng lại).
 */
return [

    /**
     * Màn "Luyện tập" bên giáo viên (teacher.assessments.index/create/store — soạn đề bằng cách
     * ghép câu rời từ Kho câu hỏi riêng).
     *
     * false = ẩn: mục "Luyện tập" biến mất khỏi menu giáo viên và 3 route trên trả 404 (kể cả
     * khi ai đó còn giữ bookmark cũ).
     *
     * KHÔNG ảnh hưởng: "Đề PDF của tôi" (teacher.papers.*), "Nhập đề Word/PDF/OCR"
     * (teacher.assessments.import + các route rà soát draft), nút Phát hành dùng chung
     * (teacher.assessments.publish — màn Đề PDF cũng dùng route này), và tab "Giao đề" trong
     * Chi tiết lớp (vẫn giao được những đề đã tạo từ trước).
     */
    'teacher_practice_screen' => env('FEATURE_TEACHER_PRACTICE_SCREEN', false),

    /**
     * Loại "Tự luyện"/"Luyện tập" (App\Enums\AssessmentType::Practice) trong ô "Loại" ở màn
     * Admin → Nội dung → Đề/bộ bài (Thêm & Sửa).
     *
     * false = ẩn: không chọn được loại này khi tạo mới hay khi sửa. Đề CŨ đang là "Tự luyện"
     * vẫn giữ nguyên loại của nó — màn Sửa hiện loại đó dạng chữ (kèm ghi chú "đang ẩn") và
     * gửi lại đúng giá trị cũ, cố ý KHÔNG âm thầm đổi sang loại khác.
     *
     * Enum AssessmentType::Practice và mọi luồng xử lý loại này giữ nguyên, không xoá.
     */
    'assessment_type_practice' => env('FEATURE_ASSESSMENT_TYPE_PRACTICE', false),

];

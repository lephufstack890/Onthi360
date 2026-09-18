<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 18/9 (khách: "làm bài rồi mà không hiển % tỉ lệ, với không chuyển chữ Làm bài thành
 * Luyện lại").
 *
 * NGUYÊN NHÂN: hai con số đó (Tỷ lệ AC của cả hệ thống + trạng thái đã-AC của chính người xem)
 * được Public\PracticeService::problemRows() đếm từ bảng attempt_answers. Nhưng màn "Luyện tập
 * theo câu" (Student\PracticeByQuestionService) CỐ Ý chỉ giữ tiến trình trong session, KHÔNG
 * tạo Attempt/AttemptAnswer nào — nên làm xong bao nhiêu bài thì hai chỗ đó vẫn đứng yên 0%,
 * nút vẫn ghi "Làm bài".
 *
 * Để ghi lại được, lượt tự luyện cần một Attempt — mà Attempt lại bắt buộc có assessment_id
 * (cột NOT NULL từ migration gốc), trong khi luyện theo câu KHÔNG thuộc đề nào cả.
 *
 * Cho phép NULL = "lượt làm bài không thuộc đề nào" (tự luyện theo câu). Cố ý KHÔNG đẻ bảng
 * mới: mọi thống kê/lịch sử sẵn có đều đang đọc attempts/attempt_answers, thêm nguồn thứ hai
 * là phải sửa mọi truy vấn và sớm muộn cũng lệch nhau. Các nơi hiển thị tên đề đã dùng sẵn
 * `?? 'Bài tập'` / `?->` nên chịu được null (đã rà: Student\PracticeService,
 * Student\DashboardService, Admin\CompetitionService...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            // change() giữ nguyên khoá ngoại + onDelete('cascade') đã có, chỉ đổi tính NULL.
            // Lượt thi thật vẫn luôn có assessment_id, cascade vẫn dọn đúng khi xoá đề.
            $table->foreignId('assessment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Lưu ý: chỉ lùi được khi KHÔNG còn lượt tự luyện nào (assessment_id NULL) trong bảng —
        // nếu còn, MySQL sẽ báo lỗi, phải xoá/gán đề cho chúng trước. Cố ý không tự xoá dữ liệu
        // của học sinh trong migration lùi.
        Schema::table('attempts', function (Blueprint $table) {
            $table->foreignId('assessment_id')->nullable(false)->change();
        });
    }
};

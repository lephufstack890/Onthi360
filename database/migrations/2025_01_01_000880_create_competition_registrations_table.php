<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 19/9 (khách: "làm logic khi click đăng ký tham gia thì admin sẽ duyệt, sau khi admin
 * duyệt xong thì học sinh sẽ vào được màn không gian thi").
 *
 * Trước đây hệ thống KHÔNG có khái niệm đăng ký cuộc thi: cứ trong khung giờ là vào thẳng đề
 * (xem ghi chú cũ ở App\Services\Public\CompetitionService). Bảng này là chỗ lưu đơn đăng ký
 * và dấu vết duyệt.
 *
 * Đặt tên cột theo ĐÚNG khuôn duyệt mới nhất của dự án (class_enrollments, SỬA 16/9):
 * trạng thái ghi vào cột 'status' dạng chuỗi, 4 cột dấu vết duyệt đều nullable. Cố ý KHÔNG
 * dùng enum DB — thêm trạng thái sau này không phải đổi lược đồ.
 *
 * unique(competition_id, student_id): mỗi học sinh chỉ có MỘT đơn cho mỗi cuộc thi — bấm đăng
 * ký nhiều lần thì cập nhật đúng dòng đó (updateOrCreate), không đẻ ra hàng đống đơn trùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // 'pending' | 'approved' | 'rejected' | 'withdrawn' — xem App\Models\CompetitionRegistration.
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'student_id']);
            // Màn duyệt của admin lọc "đơn chờ duyệt của cuộc thi này" — đi thẳng vào index.
            $table->index(['competition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_registrations');
    }
};

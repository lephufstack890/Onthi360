<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Gắn tài liệu, câu hỏi, đề thi, video, link, … vào 1 buổi học cụ thể" (note họp
        // 13/8, mục 3 "Lớp học"). Đây là danh sách TÀI NGUYÊN/GIÁO ÁN tham khảo của buổi
        // học — KHÔNG phải cơ chế phân quyền truy cập (quyền học liệu vẫn do
        // AccessGateService/class_materials/assignments quyết định, xem 7.1/7.3).
        Schema::create('session_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->string('type', 20); // Enums\SessionResourceType
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('title')->nullable(); // dùng cho video/link/note, hoặc override tên hiển thị
            $table->string('url')->nullable(); // dùng cho video/link
            $table->text('note')->nullable();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['class_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_resources');
    }
};

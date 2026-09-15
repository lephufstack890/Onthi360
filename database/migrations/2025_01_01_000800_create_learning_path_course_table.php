<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng nối Lộ trình ↔ Khoá học: mỗi dòng là MỘT BẬC của một lộ trình.
 *
 * ── Vì sao là bảng nối nhiều–nhiều, không phải cột learning_path_id trên courses ──
 * Một khoá "C++ căn bản" dùng được cho cả lộ trình lớp 9 HSG lẫn lộ trình lớp 10 chuyên.
 * Gắn một–nhiều thì muốn dùng lại là phải NHÂN BẢN khoá học — kéo theo nhân bản lớp, nhân
 * đôi dữ liệu học sinh và tiến độ. Bảng nối tốn thêm một bảng lúc này nhưng đỡ hẳn về sau.
 *
 * ── Vì sao bảng này rất nhẹ ──
 * Chỉ mang THỨ TỰ BẬC. Mã bậc / nhãn phụ / câu kết quả / số buổi nằm trên chính khoá học,
 * vì chúng đúng với khoá đó dù nó xuất hiện ở lộ trình nào. Cái duy nhất phụ thuộc ngữ cảnh
 * lộ trình là vị trí — khoá X có thể là bậc 1 ở lộ trình này và bậc 3 ở lộ trình kia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_path_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
            // Xoá lộ trình KHÔNG được xoá khoá học theo; ngược lại xoá khoá thì gỡ khỏi lộ trình.
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            // Một khoá chỉ xuất hiện đúng một lần trong cùng một lộ trình.
            $table->unique(['learning_path_id', 'course_id']);
            $table->index(['learning_path_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_course');
    }
};

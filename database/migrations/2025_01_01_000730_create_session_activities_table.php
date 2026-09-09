<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 9/9 (4) (khách: "trong mục tài nguyên buổi học thêm mục tạo hoạt động, trong hoạt động thì
 * có nhiều tài nguyên; thêm tài nguyên xong học sinh CHƯA thấy ngay, giáo viên bấm icon play thì
 * học sinh mới thấy") — 1 buổi học gồm nhiều HOẠT ĐỘNG, mỗi hoạt động gom nhiều tài nguyên.
 *
 * published_at chính là "công tắc play": NULL = giáo viên đang soạn, học sinh không thấy gì;
 * có giá trị = đã phát cho học sinh (và biết luôn phát lúc mấy giờ). Cố ý dùng mốc thời gian
 * thay cho cờ true/false vì lớp học cần biết "phát lúc nào" khi đối chiếu với giờ vào lớp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->string('title');
            $table->text('note')->nullable();
            $table->unsignedInteger('position')->default(0); // thứ tự hiện trong buổi học
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['class_session_id', 'position']);
            $table->index(['class_session_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_activities');
    }
};

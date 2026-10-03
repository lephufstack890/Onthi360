<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 3/10 (khách: "chỗ chọn khối lớp thì cho chọn nhiều nha") — questions.grade từ MỘT khối
 * (số 6-12) thành NHIỀU khối.
 *
 * Cách lưu: một chuỗi các khối ngăn bằng dấu phẩy, ví dụ "6,7,8" — giống hệt cách courses.grade
 * đã làm hồi 30/9 cho cùng yêu cầu này của khách (xem Course::splitGrades + partial
 * course-grade-field). Chọn cách đó thay vì bảng nối hay cột JSON vì:
 *   · dữ liệu cũ KHÔNG phải chuyển đổi gì — số 7 sẵn có đọc thành chuỗi "7", vẫn là 1 khối;
 *   · dự án đã có đúng khuôn này ở khoá học, thêm khuôn thứ hai cho cùng một việc là tự chuốc
 *     chỗ lệch về sau.
 *
 * Chỉ số ['subject','grade'] phải GỠ TRƯỚC rồi mới đổi kiểu cột, xong dựng lại — MySQL không
 * cho đổi kiểu một cột đang nằm trong chỉ số kiểu này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject', 'grade']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('grade', 60)->nullable()->change();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['subject', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject', 'grade']);
        });

        /*
         * Quay về 1 khối: giữ lại khối ĐẦU TIÊN của mỗi câu. Có mất mát dữ liệu — cố ý và không
         * tránh được, vì cột cũ chỉ chứa nổi một số. Làm trước khi đổi kiểu để MySQL khỏi cắt
         * chuỗi "6,7" thành 6 một cách im lặng.
         */
        DB::table('questions')
            ->whereNotNull('grade')
            ->update(['grade' => DB::raw("SUBSTRING_INDEX(grade, ',', 1)")]);

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedTinyInteger('grade')->nullable()->change();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['subject', 'grade']);
        });
    }
};

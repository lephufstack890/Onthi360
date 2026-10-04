<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/10 (khách: "bên chỗ tab giáo viên chuyên gia thêm cho tôi mục thêm giáo viên chuyên gia
 * nữa nha để người ta thêm trực tiếp bên này luôn").
 *
 * Tới giờ mỗi hồ sơ giáo viên BẮT BUỘC gắn với một tài khoản đã đăng ký và đã được duyệt. Nghĩa
 * là muốn vinh danh ai thì người đó phải tự lập tài khoản trước — không thêm thẳng được. Nới
 * user_id thành nullable để admin tạo hồ sơ "chỉ để trưng bày": một chuyên gia khách mời, một
 * thầy cô đã nghỉ, người chưa từng dùng hệ thống.
 *
 * Hồ sơ không có tài khoản thì KHÔNG CÓ lớp phụ trách, không có học viên, không có đánh giá đã
 * kiểm duyệt — những thứ đó móc theo user_id. Nên ô "Số sao xếp hạng" và "Thành tích tiêu biểu"
 * nhập tay là cách duy nhất để hồ sơ ấy có nội dung; nơi hiển thị tự ẩn các ô số rỗng.
 *
 * Ràng buộc unique giữ nguyên: MySQL cho phép NHIỀU dòng NULL trong một cột unique, nên nhiều
 * hồ sơ trưng bày cùng tồn tại được, mà một tài khoản thật vẫn chỉ có đúng một hồ sơ.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Phải GỠ KHOÁ NGOẠI trước khi đổi kiểu cột, nếu không MySQL từ chối (errno 150).
         * Tên khoá do Laravel đặt theo quy ước <bảng>_<cột>_foreign.
         */
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Hồ sơ trưng bày không có tài khoản phải dọn trước, nếu không cột không về NOT NULL được.
        \Illuminate\Support\Facades\DB::table('teacher_profiles')->whereNull('user_id')->delete();

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 14/10 (khách: "chỗ tạo/cập nhật tài liệu, khối lớp cho chọn nhiều") — cột products.grade giờ chứa NHIỀU khối ngăn
 * bằng dấu phẩy ("Lớp 6, Lớp 7"), cùng cách đã làm cho khoá học (migration add_session_range_and_multi_grade_to_courses).
 * 20 ký tự chỉ đủ 1–2 khối nên nới lên 120 (đủ cả 7 khối).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('grade', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Thu cột lại 20 ký tự thì MySQL báo lỗi ở hàng đang giữ nhiều khối — chỉ giữ khối đầu tiên trước khi thu.
        \Illuminate\Support\Facades\DB::table('products')->whereNotNull('grade')->where('grade', 'like', '%,%')->get(['id', 'grade'])
            ->each(fn ($row) => \Illuminate\Support\Facades\DB::table('products')->where('id', $row->id)
                ->update(['grade' => mb_substr(trim(explode(',', $row->grade)[0]), 0, 20)]));

        Schema::table('products', function (Blueprint $table) {
            $table->string('grade', 20)->nullable()->change();
        });
    }
};

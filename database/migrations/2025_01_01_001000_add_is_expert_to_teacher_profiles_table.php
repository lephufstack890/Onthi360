<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/10 (khách: "nếu là chuyên gia gắn badge chuyên gia cho nổi bật và luôn được lên đầu
 * danh sách trang giáo viên và chuyên gia public").
 *
 * Trang công khai tên là "Giáo viên và chuyên gia" nhưng dữ liệu trước nay chỉ có MỘT mức:
 * is_featured (được vinh danh hay không). Không có chỗ nào ghi ai là chuyên gia, nên không thể
 * gắn huy hiệu hay xếp lên đầu. Thêm đúng một cột cờ.
 *
 * is_expert ĐỘC LẬP với is_featured: chuyên gia vẫn phải được vinh danh thì mới ra trang công
 * khai (xem Public\TeacherService). Gộp hai thứ vào một cột thì mất khả năng tạm gỡ một chuyên
 * gia khỏi trang mà không quên mất người đó là chuyên gia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_profiles', 'is_expert')) {
                $table->boolean('is_expert')->default(false)->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_profiles', 'is_expert')) {
                $table->dropColumn('is_expert');
            }
        });
    }
};

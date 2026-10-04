<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/10 (khách: "thêm thứ tự hiển thị nữa để điều chỉnh ai hiển thị trước cả thêm và sửa nha").
 *
 * Trước nay thứ tự trang vinh danh do hệ thống tự quyết: chuyên gia trước, rồi tới hồ sơ sửa
 * gần nhất. Nghĩa là muốn đẩy ai lên đầu thì phải vào sửa linh tinh một chữ cho updated_at mới
 * lại — một mẹo bẩn mà người dùng phải tự nghĩ ra. Nay có cột thật để xếp.
 *
 * SỐ LỚN ĐỨNG TRƯỚC, mặc định 0 (khách chốt chiều 4/10). Hồ sơ chưa ai đặt số giữ nguyên 0 nên
 * nằm sau mọi hồ sơ đã được đẩy lên — muốn ai lên trước thì cho số cao, không phải đụng tới
 * những người còn lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_profiles', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_expert');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_profiles', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};

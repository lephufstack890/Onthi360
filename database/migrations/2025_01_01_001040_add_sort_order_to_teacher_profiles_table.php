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
 * SỐ NHỎ ĐỨNG TRƯỚC, mặc định 0. Hồ sơ mới thêm nhận số lớn nhất hiện có + 1 nên rơi xuống
 * cuối, không chen ngang lên đầu danh sách đã sắp cẩn thận (cùng cách Admin\TestimonialService
 * làm cho "Câu chuyện đồng hành").
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

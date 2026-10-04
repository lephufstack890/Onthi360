<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/10 (khách: "thêm cho tôi 1 field avatar nữa nha cả thêm và cập nhật nha").
 *
 * Trang "Giáo viên và chuyên gia" trước nay bốc ảnh đại diện MẶC ĐỊNH từ thư mục assets, xoay
 * vòng theo id — ai cũng có ảnh nhưng không ai có ảnh của mình. Nay admin tải ảnh thật lên.
 *
 * VÌ SAO ĐỂ Ở teacher_profiles CHỨ KHÔNG PHẢI users.avatar_path:
 *   · users.avatar_path là ảnh NGƯỜI DÙNG TỰ ĐẶT, hiện ở mọi nơi họ xuất hiện. Admin tải ảnh
 *     chân dung cho trang vinh danh mà ghi đè lên đó là lặng lẽ đổi ảnh cá nhân của người ta —
 *     đúng lý do đã tách display_name khỏi users.name hôm nay.
 *   · hồ sơ trưng bày không gắn tài khoản (xem migration allow_standalone_teacher_profiles)
 *     thì không có users.avatar_path nào để mà lưu.
 *
 * Thứ tự rơi khi hiển thị: ảnh ở đây -> ảnh người dùng tự đặt -> ảnh mặc định trong assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_profiles', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('display_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_profiles', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/10 (khách liệt kê các trường thông tin của giáo viên: "Họ tên / Đơn vị công tác / Vai
 * trò / Chuyên gia / Thành tích tiêu biểu / Số sao xếp hạng").
 *
 * Hồ sơ giáo viên trước nay chỉ có bio + subjects + achievement_note, nên thẻ ở trang công khai
 * phải chữa cháy: lấy MÔN DẠY đắp vào chỗ "trường công tác" và ghép chữ "Giáo viên <môn>" làm
 * vai trò. Ghi chú trong public/teachers/index.blade.php đã nói thẳng là "hệ thống chưa có 2
 * cột đó, nên hiển thị môn dạy thay vì bịa thông tin". Nay có cột thật.
 *
 * display_name: TÊN HIỂN THỊ trên trang vinh danh, KHÔNG phải tên tài khoản. Để trống thì lấy
 * users.name. Tách ra vì tên trên trang vinh danh hay có học hàm học vị ("TS. Nguyễn Văn An")
 * mà tên tài khoản là thứ người đó tự đặt và dùng để đăng nhập — admin sửa tên tài khoản của
 * người khác từ màn này là đi quá xa.
 *
 * display_rating: SỐ SAO DO BAN QUẢN TRỊ CÔNG BỐ, tách hẳn khỏi điểm trung bình tính từ đánh
 * giá đã kiểm duyệt (bảng rating_summaries). KHÔNG gộp hai thứ vào một chỗ: trang công khai
 * đang ghi "Đánh giá trung bình đã xác thực", đổ một con số admin tự gõ vào dưới dòng chữ ấy
 * là nói sai với người đọc. Nơi hiển thị sẽ đổi nhãn theo nguồn số, xem Public\TeacherService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_profiles', 'display_name')) {
                $table->string('display_name', 120)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('teacher_profiles', 'workplace')) {
                $table->string('workplace', 160)->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('teacher_profiles', 'role_title')) {
                $table->string('role_title', 120)->nullable()->after('workplace');
            }
            if (! Schema::hasColumn('teacher_profiles', 'display_rating')) {
                // 0.0 - 5.0, một chữ số thập phân — đúng cách trang công khai đang hiện (4.8).
                $table->decimal('display_rating', 2, 1)->nullable()->after('is_expert');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            foreach (['display_name', 'workplace', 'role_title', 'display_rating'] as $column) {
                if (Schema::hasColumn('teacher_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

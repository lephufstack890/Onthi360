<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bốn trường "bậc" của khoá học, đọc thẳng từ ảnh lộ trình khách gửi.
 *
 * Ví dụ bậc 2 trong ảnh: mã bậc FOUNDATION A · nhãn phụ CORE · kết quả "Viết code đúng" ·
 * 14 buổi.
 *
 * ── Vì sao để trên courses chứ không để trên bảng nối ──
 * Khoá "FOUNDATION A 14 buổi" thì ở lộ trình nào nó cũng là 14 buổi và cũng dạy viết code
 * đúng. Chỉ có VỊ TRÍ bậc mới đổi theo lộ trình, nên chỉ vị trí nằm ở bảng nối.
 *
 * ── Vì sao mã bậc là chuỗi tự do, chưa làm bảng course_levels ──
 * Khách hiện có HAI bộ tên bậc khác nhau (PRE-CODE/FOUNDATION/INTERMEDIATE... ở ảnh mới, và
 * Khởi động/Nền tảng/Thực hành... ở bộ SVG cũ) và chưa chốt bộ nào là chuẩn. Để chuỗi tự do
 * thì nhập được ngay cả hai kiểu. Khi nào khách chốt một thang chuẩn dùng lại cho mọi lộ
 * trình thì thêm bảng course_levels và đổi cột này thành khoá ngoại — dữ liệu đã nhập vẫn
 * chuyển sang được vì mã bậc là duy nhất theo tên.
 *
 * ── Vì sao "số buổi thiết kế" khác class_sessions ──
 * class_sessions là buổi học THẬT đã xếp lịch của một lớp cụ thể (có ngày giờ, phòng học).
 * session_count ở đây là số buổi THEO CHƯƠNG TRÌNH của khoá. Hai con số khác nhau, và màn
 * quản trị hiện cả hai cạnh nhau để phát hiện lớp xếp thiếu buổi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('level_code', 60)->nullable()->after('grade');
            $table->string('level_subtitle', 60)->nullable()->after('level_code');
            $table->string('outcome', 160)->nullable()->after('level_subtitle');
            $table->unsignedSmallInteger('session_count')->nullable()->after('outcome');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['level_code', 'level_subtitle', 'outcome', 'session_count']);
        });
    }
};

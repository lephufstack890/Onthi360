<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 16/9 — bốn trường mô tả lớp mà THẺ LỚP ngoài trang công khai cần in ra (bản dựng theo
 * education-main-12/.../CoursesPage.jsx): hình thức học, nơi học, địa chỉ và sĩ số tối đa.
 *
 * Trước đây bảng class_rooms chỉ có mã lớp, tên lớp, lịch học (ghi chú tự do) và trạng thái —
 * không có chỗ nào lưu "Trực tuyến / Tại lớp", "Live + ghi hình" hay "32/40 học sinh", nên thẻ
 * lớp phải bỏ trống đúng những dòng mà bản thiết kế nhấn mạnh nhất.
 *
 * Tất cả đều nullable: lớp cũ không có dữ liệu vẫn chạy bình thường, thẻ tự giấu dòng tương
 * ứng thay vì in ô rỗng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_rooms', function (Blueprint $table) {
            // "Trực tuyến" / "Tại trung tâm" / "Kết hợp" — chuỗi tự do, không ép enum vì mỗi
            // trung tâm gọi một kiểu và đổi cách gọi không nên phải chạy migration.
            $table->string('location', 60)->nullable()->after('name');
            // Địa chỉ hoặc tên phòng học. Lớp trực tuyến thường bỏ trống.
            $table->string('address', 160)->nullable()->after('location');
            // "Live + ghi hình", "Live", "Ghi hình"...
            $table->string('format', 60)->nullable()->after('address');
            // Sĩ số TỐI ĐA. Sĩ số thực tế luôn đếm từ bảng ghi danh, không lưu ở đây, để hai số
            // không bao giờ lệch nhau.
            $table->unsignedSmallInteger('capacity')->nullable()->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->dropColumn(['location', 'address', 'format', 'capacity']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 30/9 (khách) — 2 việc ở màn Thêm/Sửa khoá học:
 *
 *  1. "Số buổi ... nó kiểu 33-50 buổi nên tôi thêm không được": số buổi theo chương trình của
 *     khoá thực tế là một KHOẢNG, mà ô nhập cũ chỉ nhận đúng 1 số nguyên. Thêm cột
 *     session_count_max — session_count thành cận DƯỚI, cột mới là cận TRÊN (bỏ trống = số
 *     buổi cố định, hiển thị y như cũ). Cố ý KHÔNG đổi session_count thành chuỗi "33-50":
 *     cột đó đang được cộng để ra tổng buổi của lộ trình (LearningPath::totalSessions()) và
 *     đối chiếu với số buổi đã xếp lịch — đổi sang chuỗi là hỏng hết mấy chỗ đó.
 *
 *  2. "Chỗ chọn khối và lớp thì cho chọn nhiều": 1 khoá dạy được nhiều khối (vd Lớp 6 + Lớp
 *     7). Vẫn giữ 1 cột grade (không thêm bảng nối) nhưng nới từ 20 lên 120 ký tự để chứa
 *     danh sách ngăn bằng dấu phẩy — "Lớp 10, Lớp 11, Lớp 12" đã 22 ký tự, cột cũ cắt cụt
 *     mất dữ liệu. Mọi nơi đọc grade vẫn là đọc 1 chuỗi như trước.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedSmallInteger('session_count_max')->nullable()->after('session_count');
            $table->string('grade', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('session_count_max');
            // Cắt bớt trước khi thu cột, nếu không MySQL báo lỗi ở hàng đang giữ nhiều khối.
            $table->string('grade', 20)->nullable()->change();
        });
    }
};

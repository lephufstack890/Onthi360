<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 16/9 (khách yêu cầu: "bỏ chỗ nhập mã lớp đi. Học sinh vào trang lớp học ngoài public bấm
 * đăng ký học thì giáo viên duyệt rồi học sinh được vào học").
 *
 * Trước đây class_enrollments chỉ có active|left: đã ghi danh là VÀO ĐƯỢC LỚP NGAY, không có
 * bước nào ở giữa — nên lối vào duy nhất phải là mã lớp do giáo viên đưa riêng. Thêm trạng thái
 * CHỜ DUYỆT ('pending', ghi thẳng vào cột status sẵn có, không cần đổi kiểu cột) cùng 4 cột dấu
 * vết dưới đây để giáo viên biết ai xin vào lúc nào, ai đã duyệt và vì sao bị từ chối.
 *
 * AccessGateService::canAccessClassRoom() vốn CHỈ chấp nhận status='active', nên dòng 'pending'
 * tự động KHÔNG vào học được — không phải sửa cửa quyền, đó là lý do chọn cách này.
 *
 * Tất cả nullable: dòng ghi danh cũ (trước tính năng duyệt) không có dấu vết vẫn chạy bình thường.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table) {
            // Lúc học sinh bấm "Đăng ký học". Khác enrolled_at: enrolled_at là lúc THỰC SỰ vào lớp.
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('requested_at');
            // Giáo viên/quản trị đã bấm duyệt. nullOnDelete: xoá tài khoản giáo viên không được
            // làm mất dòng ghi danh của học sinh.
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->string('reject_reason', 255)->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['requested_at', 'approved_at', 'reject_reason']);
        });
    }
};

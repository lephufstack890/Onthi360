<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 9/10 (khách: "trang tài liệu có cả giao tài liệu, source mới có hết rồi") — bảng lưu LƯỢT
 * GIAO TÀI LIỆU riêng cho từng học sinh, dựng theo education-main (QuickAssignButton kiểu
 * "material", materialAssignmentStatus, getMaterialAccess nguồn "assignment").
 *
 * Một lượt giao = giáo viên/admin chọn một tài liệu (sản phẩm), một học sinh, kèm:
 *   · deadline_at        HẠN ĐỌC mà giáo viên đặt (để theo dõi, quá hạn mà chưa mở thì báo "Quá hạn đọc");
 *   · access_days        thời hạn CẤP QUYỀN ĐỌC (7/30/90/365 ngày, tính từ lúc giao);
 *   · access_expires_at  = lúc giao + access_days. Đồng thời sinh một dòng access_rights
 *                        (source = 'assignment', source_id = id lượt giao) để học sinh thấy tài liệu
 *                        trong "Tài liệu của tôi" và mở đọc được trong hạn đó;
 *   · note               lời nhắn cho học sinh;
 *   · opened_at          lần đầu học sinh MỞ tài liệu sau khi được giao ("Đã mở" chỉ ghi nhận việc
 *                        mở, không có nghĩa đã đọc xong — bản mẫu cũng nói rõ như vậy).
 *
 * Giao lại cùng một (người giao, học sinh, tài liệu) thì CẬP NHẬT lượt cũ (đặt lại hạn, quyền, xoá
 * opened_at) chứ không sinh dòng mới — ràng buộc unique bên dưới bảo đảm kể cả khi bấm đúp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_assignments')) {
            return;
        }

        Schema::create('material_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->dateTime('deadline_at');
            $table->unsignedSmallInteger('access_days');
            $table->dateTime('access_expires_at');
            $table->text('note')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->unsignedBigInteger('access_right_id')->nullable();
            $table->timestamps();

            $table->unique(['assigned_by', 'student_id', 'product_id'], 'material_assignments_unique_slot');
            $table->index(['student_id', 'product_id'], 'material_assignments_student_idx');
            $table->index(['assigned_by'], 'material_assignments_teacher_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_assignments');
    }
};

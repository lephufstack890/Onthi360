<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 18/9 (khách: "thêm tính năng thêm mã kích hoạt... mã kích hoạt này thuộc tài liệu nào và
 * thuộc tài khoản nào thì tài khoản đó mở được thôi. Admin cấp mà mở cho tài khoản khác là
 * không mở được").
 *
 * Trước đây mã kích hoạt CHỈ gắn với sản phẩm (product_id) — ai cầm mã cũng kích hoạt được,
 * vì mã vốn sinh ra từ đơn hàng và đưa tận tay người mua. Giờ admin tự cấp mã cho 1 tài khoản
 * cụ thể nên cần khoá thêm theo NGƯỜI:
 *
 *   assigned_user_id = NULL  -> mã sinh từ đơn hàng như trước, không đổi hành vi cũ
 *   assigned_user_id ≠ NULL  -> CHỈ đúng tài khoản đó kích hoạt được
 *
 * Chặn thật nằm ở App\Services\OrderActivationService::canActivate() (cửa duy nhất cho cả
 * nhánh xem trước ?code=... lẫn nhánh bấm Kích hoạt), không phải chỉ ẩn ở giao diện.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_codes', function (Blueprint $table) {
            // nullOnDelete: xoá tài khoản thì mã KHÔNG bị xoá lây (còn là bằng chứng đã cấp),
            // chỉ mất ràng buộc người nhận — cùng quy ước với activated_by ngay bên cạnh.
            $table->foreignId('assigned_user_id')->nullable()->after('product_id')
                ->constrained('users')->nullOnDelete();
            // Ghi chú của admin khi cấp tay: cấp cho ai/vì sao (hỗ trợ, tặng, đền bù...).
            $table->string('note', 500)->nullable()->after('validity_months');
            $table->foreignId('created_by')->nullable()->after('note')
                ->constrained('users')->nullOnDelete();

            // Tra "tài khoản này đang có mã nào chưa dùng" ở màn cấp mã (chặn cấp trùng).
            $table->index(['assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('activation_codes', function (Blueprint $table) {
            $table->dropIndex(['assigned_user_id', 'status']);
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('note');
        });
    }
};

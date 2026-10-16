<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 16/10 (khách: "thêm 2 nút mũi tên lên/xuống cạnh Xem/Sửa ở danh sách Tài liệu cho cả 3 tab Sách / Chuyên đề / Bộ đề;
 * ngoài trang Tài liệu public cũng hiện đúng thứ tự này") — thứ tự hiển thị tự chỉnh tay của tài liệu, SỐ NHỎ HIỆN TRƯỚC,
 * đánh riêng trong từng loại (Sách / Chuyên đề / Bộ đề). Xem Product::scopeOrdered() + Admin\ProductService::move().
 *
 * Mặc định 0 cho mọi tài liệu cũ: khi bằng nhau thì xếp mới nhất trước — ĐÚNG thứ tự đang thấy trước khi deploy, nên chạy
 * migration xong không có gì nhảy chỗ. Tài liệu tạo mới (0) hiện đầu nhóm cho tới khi được đẩy xuống.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'sort_order')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('visibility');
            $table->index(['type', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'sort_order')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['type', 'sort_order']);
                $table->dropColumn('sort_order');
            });
        }
    }
};

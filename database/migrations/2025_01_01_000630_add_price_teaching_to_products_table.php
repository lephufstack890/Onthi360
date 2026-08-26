<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // "Giá để dạy" — tách riêng khỏi 'price' ("Giá để học"), theo yêu cầu
            // 26/8: 1 sản phẩm giờ có 2 giá tuỳ theo scope mua (Học cá nhân /
            // Dùng để dạy) — xem AccessService::placeOrder().
            $table->unsignedInteger('price_teaching')->default(0)->after('price');
        });

        // Backfill: sản phẩm đã tồn tại trước migration này chưa từng có "giá dạy"
        // riêng — gán bằng giá học hiện tại (an toàn hơn mặc định 0, tránh vô tình
        // làm "giá dạy" miễn phí cho toàn bộ sản phẩm cũ).
        DB::table('products')->update(['price_teaching' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_teaching');
        });
    }
};

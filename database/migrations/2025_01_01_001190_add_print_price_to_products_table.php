<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 14/10 (khách: "kick Có bản in thì có thêm tiền bản in, giá + giá bản in ra giá chính") — giá bản in RIÊNG của từng
 * tài liệu, thay cho phụ phí in cố định 50.000đ trước đây (AccessService::PRINT_PRICE).
 *
 * Backfill: tài liệu đang bật "Có bản in" lấy đúng 50.000đ cũ, để giá khách đang thấy không đổi sau khi deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'print_price')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('print_price')->default(0)->after('has_print_option');
        });

        DB::table('products')->where('has_print_option', true)->update(['print_price' => 50000]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'print_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('print_price');
            });
        }
    }
};

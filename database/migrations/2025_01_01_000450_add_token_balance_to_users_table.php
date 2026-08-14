<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Nộp tiền thành token" (note họp 13/8, mục 7) — số dư token dùng chung cho nhiều lần
 * thanh toán sau này (đăng ký thi, mua học liệu, ...) thay vì chuyển khoản riêng lẻ từng
 * đơn. Xem App\Services\WalletService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('token_balance')->default(0)->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('token_balance');
        });
    }
};

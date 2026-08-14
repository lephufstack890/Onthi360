<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Người dùng cần có thuộc tính tỉnh thành và khu vực để quảng cáo cho giáo viên"
        // (note họp 13/8, mục 2) — dùng để sau này lọc/gợi ý giáo viên theo khu vực gần
        // học sinh/phụ huynh. region là 1 trong 3 giá trị macro (Bắc/Trung/Nam, xem
        // App\Support\VietnamProvinces::regionOptions()) — không ràng buộc FK, chỉ để lọc.
        Schema::table('users', function (Blueprint $table) {
            $table->string('province', 100)->nullable()->after('phone');
            $table->string('region', 20)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['province', 'region']);
        });
    }
};

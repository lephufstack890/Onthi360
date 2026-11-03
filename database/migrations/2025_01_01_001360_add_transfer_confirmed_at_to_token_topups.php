<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 3/11 (khách: "chuyển khoản xong bấm nút thì báo admin, lúc đó trạng thái mới chuyển sang Chờ duyệt") —
 * token_topups.transfer_confirmed_at = lúc người nạp bấm "Tôi đã chuyển khoản".
 *   · null  → "Chờ chuyển khoản" (mới tạo yêu cầu, chưa báo đã chuyển);
 *   · có giá trị → "Chờ duyệt" (đã báo, admin được thông báo để đối soát).
 * Yêu cầu đang chờ từ TRƯỚC khi có cột này coi như đã báo chuyển khoản (giữ nguyên "Chờ duyệt" như người dùng đang thấy).
 * Chạy lại an toàn (đã có cột thì bỏ qua).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('token_topups', 'transfer_confirmed_at')) {
            return;
        }

        Schema::table('token_topups', function (Blueprint $table) {
            $table->timestamp('transfer_confirmed_at')->nullable()->after('status');
        });

        DB::table('token_topups')->where('status', 'pending')->update(['transfer_confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('token_topups', 'transfer_confirmed_at')) {
            Schema::table('token_topups', function (Blueprint $table) {
                $table->dropColumn('transfer_confirmed_at');
            });
        }
    }
};

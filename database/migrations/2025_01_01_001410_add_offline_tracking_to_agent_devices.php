<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/11 — Chocon Desktop: theo dõi máy mất kết nối để báo phụ huynh.
 *  · last_stop_reason     lý do phần mềm dừng lần cuối: shutdown | sleep | updating | stopped (null = đang chạy)
 *  · offline_notified_at  đã báo phụ huynh "mất kết nối" cho lần mất kết nối này chưa
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_devices', 'last_stop_reason')) {
                $table->string('last_stop_reason', 20)->nullable()->after('locked');
            }
            if (! Schema::hasColumn('agent_devices', 'offline_notified_at')) {
                $table->dateTime('offline_notified_at')->nullable()->after('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_devices', function (Blueprint $table) {
            $table->dropColumn(['last_stop_reason', 'offline_notified_at']);
        });
    }
};

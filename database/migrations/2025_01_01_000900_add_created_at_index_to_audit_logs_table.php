<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 23/9 (khách: "vào admin 500") — lỗi thật từ MySQL:
 *
 *   SQLSTATE[HY001]: 1038 Out of sort memory, consider increasing server sort buffer size
 *   SQL: select * from `audit_logs` order by `created_at` desc limit 10
 *
 * Bảng audit_logs KHÔNG có chỉ mục trên created_at, nên để sắp xếp MySQL phải đọc toàn bộ bảng
 * rồi sắp trong bộ nhớ (filesort). Mỗi dòng lại có cột JSON 'changes' rất nặng, log càng nhiều
 * thì vùng nhớ sắp xếp càng thiếu — tới một ngưỡng là văng lỗi 1038 và trang Tổng quan của
 * admin trả 500.
 *
 * Thêm 2 chỉ mục để MySQL đọc sẵn theo thứ tự thời gian, khỏi phải sắp xếp:
 *   · (created_at, id)                        -> Admin\DashboardService: hoạt động gần đây
 *   · (auditable_type, auditable_id, created_at) -> Admin\UserService: lịch sử của 1 đối tượng
 *
 * Có guard đầy đủ để chạy lại nhiều lần cũng không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! $this->hasIndex('audit_logs', 'audit_logs_created_at_id_index')) {
                $table->index(['created_at', 'id'], 'audit_logs_created_at_id_index');
            }

            if (! $this->hasIndex('audit_logs', 'audit_logs_auditable_created_at_index')) {
                $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_created_at_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if ($this->hasIndex('audit_logs', 'audit_logs_created_at_id_index')) {
                $table->dropIndex('audit_logs_created_at_id_index');
            }

            if ($this->hasIndex('audit_logs', 'audit_logs_auditable_created_at_index')) {
                $table->dropIndex('audit_logs_auditable_created_at_index');
            }
        });
    }

    /** Chỉ hỏi MySQL, không dùng doctrine/dbal (dự án không cài gói đó). */
    private function hasIndex(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]) !== [];
    }
};

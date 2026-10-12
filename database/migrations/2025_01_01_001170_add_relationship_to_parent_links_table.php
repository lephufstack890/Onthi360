<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/10 — mối quan hệ giữa phụ huynh và học sinh (Mẹ / Bố / Người giám hộ), theo trang
 * "Phụ huynh" của khu Học sinh trong source mới (StudentParentsPanel.jsx).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('parent_links', 'relationship')) {
            Schema::table('parent_links', function (Blueprint $table) {
                $table->string('relationship', 30)->nullable()->after('verification_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('parent_links', 'relationship')) {
            Schema::table('parent_links', function (Blueprint $table) {
                $table->dropColumn('relationship');
            });
        }
    }
};

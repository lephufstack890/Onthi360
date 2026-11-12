<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/11 (khách: "loại tag là Lập trình và Phổ thông, không phải Thủ công") — đổi giá trị tags.kind 'manual' → 'general'.
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'kind')) {
            DB::table('tags')->where('kind', 'manual')->update(['kind' => 'general']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'kind')) {
            DB::table('tags')->where('kind', 'general')->update(['kind' => 'manual']);
        }
    }
};

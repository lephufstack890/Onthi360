<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 9/9 (4) — mỗi tài nguyên buổi học giờ thuộc về 1 HOẠT ĐỘNG (xem migration
 * create_session_activities_table).
 *
 * Nullable vì tài nguyên gắn TRƯỚC thay đổi này chưa thuộc hoạt động nào — chúng vẫn hiện ở màn
 * điểm danh trong nhóm "Chưa thuộc hoạt động nào" để giáo viên xem/gỡ, KHÔNG bị mất dữ liệu.
 * Xoá hoạt động thì xoá luôn tài nguyên bên trong (cascade): tài nguyên chỉ có nghĩa khi nằm
 * trong hoạt động của nó, để lại sẽ thành mục mồ côi không ai thấy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_resources', function (Blueprint $table) {
            $table->foreignId('activity_id')
                ->nullable()
                ->after('class_session_id')
                ->constrained('session_activities')
                ->cascadeOnDelete();

            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });
    }
};

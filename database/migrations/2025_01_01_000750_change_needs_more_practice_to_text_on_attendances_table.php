<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 9/9 (12) (khách: "chỗ 'Em cần học thêm' đừng để dạng check nha mà để dạng nhập như
 * Nhận xét để người ta nhập").
 *
 * Trước đây cột này là boolean — giáo viên chỉ tick được có/không, không ghi được CẦN HỌC
 * THÊM CÁI GÌ. Đổi thành cột chữ để nhập tự do như ô "Nhận xét".
 *
 * Dữ liệu cũ KHÔNG mất: dòng nào đang tick sẽ được ghi sẵn một câu để giáo viên biết trước
 * đây mình đã đánh dấu học sinh này và sửa lại cho cụ thể.
 */
return new class extends Migration
{
    private const MIGRATED_TEXT = 'Cần học thêm (đánh dấu từ bản cũ — thầy/cô ghi rõ giúp em cần bổ sung phần nào).';

    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->text('needs_more_practice_note')->nullable()->after('needs_more_practice');
        });

        DB::table('attendances')
            ->where('needs_more_practice', true)
            ->update(['needs_more_practice_note' => self::MIGRATED_TEXT]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('needs_more_practice');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('needs_more_practice')->default(false)->after('note');
        });

        DB::table('attendances')
            ->whereNotNull('needs_more_practice_note')
            ->where('needs_more_practice_note', '<>', '')
            ->update(['needs_more_practice' => true]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('needs_more_practice_note');
        });
    }
};

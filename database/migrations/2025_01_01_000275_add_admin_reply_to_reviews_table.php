<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 9.4: "Giáo viên không sửa/xóa review; chỉ Admin có thể đăng phản hồi chính thức sau khi
     * review công bố. Phản hồi không che hoặc làm lại điểm sao." — cần lưu phản hồi tách biệt
     * khỏi nội dung review gốc (không sửa đè comment/overall_rating của người viết).
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('admin_reply')->nullable()->after('moderation_reason');
            $table->foreignId('admin_reply_by')->nullable()->after('admin_reply')->constrained('users')->nullOnDelete();
            $table->dateTime('admin_reply_at')->nullable()->after('admin_reply_by');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_reply_by');
            $table->dropColumn(['admin_reply', 'admin_reply_at']);
        });
    }
};

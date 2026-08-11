<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log tối giản cho các thao tác nhạy cảm (16 mục 4): phê duyệt giáo
     * viên, cấp/thu quyền, đơn/mã/kích hoạt, gắn/gỡ học liệu, mở/đóng tiến độ,
     * phát hành đề, thay đổi điểm, moderation review. Xem App\Concerns\Auditable.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // created|updated|deleted
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('changes')->nullable(); // {"field": {"old": ..., "new": ...}}
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

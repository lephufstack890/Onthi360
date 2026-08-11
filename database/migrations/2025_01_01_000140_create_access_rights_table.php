<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Quyền học cá nhân" / "Quyền dùng để dạy" — 5.1, 7.2. class_limit = null nghĩa là
        // unlimited (bắt buộc với scope=teacher_teaching theo 5.3/7.2 — ràng buộc ở tầng ứng dụng,
        // xem App\Services\AccessGateService).
        Schema::create('access_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('scope', 30); // Enums\AccessScope
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('pending'); // Enums\AccessRightStatus
            $table->unsignedInteger('class_limit')->nullable(); // null = unlimited
            $table->string('source', 30); // order|gift|admin_grant|package
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'product_id', 'scope', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_rights');
    }
};

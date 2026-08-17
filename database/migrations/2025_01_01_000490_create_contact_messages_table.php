<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tin nhắn gửi từ form "Liên hệ" ở trang công khai info.index (PUB-11, 4.1) — trước đây
 * form này chỉ là <button type="button"> tĩnh, không gửi đi đâu cả. Ai cũng gửi được, kể cả
 * khách chưa đăng nhập (không có reviewer_id/user_id) nên không dùng lại bảng reviews/khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->string('status', 20)->default('new'); // Enums\ContactMessageStatus
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng "notifications" theo ĐÚNG schema mặc định của Illuminate Notifications
 * (kênh 'database') — App\Models\User đã dùng trait Notifiable sẵn. Dùng lại hệ thống
 * gốc của framework thay vì bảng tự chế để không xung đột nếu có notification channel
 * khác trong app cùng ghi vào bảng này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

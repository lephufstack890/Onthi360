<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/11 — Chocon Desktop (phần mềm quản lý máy tính của con, Giai đoạn 2–5):
 *  · agent_policies            luật phụ huynh đặt cho từng con (áp dụng cho mọi máy của con đó)
 *  · agent_devices             máy tính đã ghép (token chỉ lưu dạng băm SHA-256)
 *  · agent_pairing_codes       mã 6 số để ghép máy, hết hạn sau 15 phút
 *  · agent_activity_sessions   phiên sử dụng máy gửi lên (học / game / web / khác) — id do máy sinh để chống trùng
 *  · agent_events              sự kiện (đóng game giờ cấm, thử gỡ phần mềm, chỉnh giờ...)
 *  · agent_extra_time_requests con xin thêm giờ chơi, phụ huynh duyệt
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lần chạy đầu từng lỗi giữa chừng (MySQL strict) → các bảng đã tạo dở có kiểu cột sai.
        // Bảng cuối chưa có nghĩa là chưa từng chạy xong: xóa để tạo lại.
        if (! Schema::hasTable('agent_activity_sessions')) {
            Schema::dropIfExists('agent_pairing_codes');
            Schema::dropIfExists('agent_devices');
            Schema::dropIfExists('agent_policies');
        }

        if (! Schema::hasTable('agent_policies')) {
            Schema::create('agent_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('daily_game_minutes')->nullable();      // null = không giới hạn
                $table->boolean('study_only_mode')->default(false);                  // chế độ chỉ học luôn bật
                $table->boolean('study_only_in_study_hours')->default(true);         // trong giờ học: chỉ học
                $table->string('study_hours_start', 5)->nullable();                  // giờ học Thứ 2–6, "HH:MM"
                $table->string('study_hours_end', 5)->nullable();
                $table->string('sleep_start', 5)->nullable();                        // giờ ngủ mọi ngày
                $table->string('sleep_end', 5)->nullable();
                $table->json('allowed_domains')->nullable();
                $table->json('blocked_domains')->nullable();
                $table->json('blocked_processes')->nullable();
                $table->boolean('disable_private_browsing')->default(true);
                $table->boolean('game_requires_study')->default(false);
                $table->unsignedTinyInteger('study_goal_items')->default(3);         // số bài/đề phải nộp mỗi ngày
                $table->unsignedTinyInteger('bonus_minutes_per_item')->default(0);   // phút chơi thưởng mỗi bài nộp
                $table->unsignedSmallInteger('bonus_cap_minutes')->default(60);
                $table->boolean('lock_now')->default(false);
                $table->string('lock_message', 200)->nullable();
                $table->string('parent_pin_hash', 200)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_devices')) {
            Schema::create('agent_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('machine_id', 64)->nullable();
                $table->char('token_hash', 64)->unique();
                $table->string('agent_version', 20)->nullable();
                $table->string('os_version', 150)->nullable();
                $table->unsignedInteger('policy_version')->default(0);
                $table->boolean('tray_connected')->default(false);
                $table->boolean('enforcement_enabled')->default(false);
                $table->boolean('locked')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['student_user_id', 'revoked_at']);
                $table->index('machine_id');
            });
        }

        if (! Schema::hasTable('agent_pairing_codes')) {
            Schema::create('agent_pairing_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->dateTime('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->foreignId('device_id')->nullable()->constrained('agent_devices')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_activity_sessions')) {
            Schema::create('agent_activity_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('device_id')->constrained('agent_devices')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->dateTime('started_at');
                $table->dateTime('ended_at');
                $table->unsignedInteger('duration_seconds');
                $table->string('process_name', 100);
                $table->string('window_title', 200)->nullable();
                $table->string('domain', 190)->nullable();
                $table->string('category', 20);                                      // study|game|browser|other|system|unknown
                $table->timestamp('created_at')->nullable();

                $table->index(['student_user_id', 'started_at']);
            });
        }

        if (! Schema::hasTable('agent_events')) {
            Schema::create('agent_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('device_id')->constrained('agent_devices')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 40);
                $table->dateTime('occurred_at');
                $table->json('data')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['student_user_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('agent_extra_time_requests')) {
            Schema::create('agent_extra_time_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->constrained('agent_devices')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('minutes');
                $table->string('reason', 200)->nullable();
                $table->string('status', 20)->default('pending');                    // pending|approved|rejected
                $table->date('for_date');
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['student_user_id', 'for_date', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_extra_time_requests');
        Schema::dropIfExists('agent_events');
        Schema::dropIfExists('agent_activity_sessions');
        Schema::dropIfExists('agent_pairing_codes');
        Schema::dropIfExists('agent_devices');
        Schema::dropIfExists('agent_policies');
    }
};

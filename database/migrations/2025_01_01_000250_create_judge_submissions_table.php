<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cầu nối tới judge/runner riêng (không chạy trong tiến trình web) — 16 mục 1.
        Schema::create('judge_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_answer_id')->constrained('attempt_answers')->cascadeOnDelete();
            $table->string('external_submission_id')->nullable()->index(); // id bên judge/OJ
            $table->string('status', 30)->default('queued'); // queued|dispatched|completed|failed
            $table->string('verdict', 30)->nullable(); // Enums\VerdictStatus khi có kết quả
            $table->json('raw_result')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_submissions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng cache tổng hợp — cập nhật bởi listener khi review được publish (9.5).
        // Tách khỏi bảng reviews để đọc nhanh trên card/listing mà không JOIN/COUNT mỗi lần.
        Schema::create('rating_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->json('distribution')->nullable(); // {"1": n, "2": n, ..., "5": n}
            $table->dateTime('updated_at_summary')->nullable();
            $table->timestamps();

            $table->unique(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_summaries');
    }
};

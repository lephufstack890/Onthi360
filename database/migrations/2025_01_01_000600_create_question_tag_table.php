<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bảng trung gian nhiều-nhiều Question <-> Tag (Giai đoạn 6 — xem create_tags_table.php). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['question_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tag');
    }
};

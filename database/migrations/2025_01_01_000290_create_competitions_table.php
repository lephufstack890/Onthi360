<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type', 20); // Enums\CompetitionType (contest|survey)
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->text('rules')->nullable(); // thể lệ
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('publish_result_at')->nullable();
            $table->string('status', 20)->default('upcoming'); // Enums\CompetitionStatus
            $table->json('ranking_rule')->nullable(); // công thức điểm/penalty/đồng điểm (11.2)
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};

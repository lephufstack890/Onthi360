<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20); // competition|class_room|topic — 11.2 "phạm vi rõ"
            $table->foreignId('competition_id')->nullable()->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('class_room_id')->nullable()->constrained('class_rooms')->cascadeOnDelete();
            $table->string('topic', 120)->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 10, 2)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->json('tie_break')->nullable();
            $table->dateTime('computed_at')->useCurrent();
            $table->timestamps();

            $table->index(['scope', 'competition_id', 'class_room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};

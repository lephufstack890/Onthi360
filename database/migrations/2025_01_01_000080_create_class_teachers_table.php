<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('main'); // main|co_teacher — dùng cho "đồng phụ trách" (7.2)
            $table->timestamps();

            $table->unique(['class_room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_teachers');
    }
};

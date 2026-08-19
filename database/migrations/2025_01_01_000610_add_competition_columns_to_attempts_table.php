<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->foreignId('competition_id')->nullable()->after('assignment_id')
                ->constrained('competitions')->nullOnDelete();
            $table->foreignId('competition_exam_id')->nullable()->after('competition_id')
                ->constrained('competition_exams')->nullOnDelete();

            $table->index(['user_id', 'competition_id']);
            $table->index(['user_id', 'competition_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'competition_id']);
            $table->dropIndex(['user_id', 'competition_exam_id']);
            $table->dropConstrainedForeignId('competition_id');
            $table->dropConstrainedForeignId('competition_exam_id');
        });
    }
};

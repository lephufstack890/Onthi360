<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 25/10 (khách: "chọn được nhiều đề trong 1 kỳ, học sinh làm thì random 1 đề bất kỳ") — các đề lấy từ ngân hàng
 * của một kỳ (nguồn "Lấy đề có sẵn"). competition_exams.assessment_id vẫn là đề ĐẦU TIÊN (để mọi chỗ cũ chạy như cũ);
 * bảng này giữ đủ danh sách. Kỳ chưa có dòng nào = chỉ 1 đề là assessment_id. Xem CompetitionExam::bankAssessmentIds().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_exam_assessments')) {
            return;
        }

        Schema::create('competition_exam_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_exam_id')->constrained('competition_exams')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['competition_exam_id', 'assessment_id'], 'cea_exam_assessment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_exam_assessments');
    }
};

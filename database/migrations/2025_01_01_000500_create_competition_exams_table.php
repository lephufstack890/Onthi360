<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * competition_exams — 1 Competition có thể gồm NHIỀU kỳ thi (vd: Vòng 1, Vòng 2...), mỗi
 * kỳ thi tham chiếu 1 Assessment riêng (11.1: "cuộc thi chỉ tham chiếu đề để tổ chức sự
 * kiện"). Trước đây Competition::assessment_id chỉ cho phép DUY NHẤT 1 đề tham chiếu —
 * bảng này mở rộng thành quan hệ 1-nhiều mà KHÔNG xoá cột assessment_id cũ (giữ tương
 * thích ngược cho các cuộc thi/luồng cũ đang phụ thuộc cột đó, ví dụ
 * App\Services\Public\CompetitionService::showData()'s canJoinDirectly cũ).
 *
 * Backfill: mỗi Competition đang có assessment_id được tạo sẵn 1 dòng competition_exams
 * tương ứng (order=1, không có starts_at/ends_at riêng — dùng chung lịch của Competition),
 * để cuộc thi cũ hiển thị đúng ngay trong danh sách kỳ thi mới mà không cần admin nhập lại
 * tay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('title')->nullable(); // vd "Vòng 1" — rỗng thì hiển thị tên đề tham chiếu
            $table->unsignedInteger('order')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'order']);
        });

        $now = now();

        DB::table('competitions')
            ->whereNotNull('assessment_id')
            ->select('id', 'assessment_id')
            ->orderBy('id')
            ->get()
            ->each(function ($competition) use ($now) {
                DB::table('competition_exams')->insert([
                    'competition_id' => $competition->id,
                    'assessment_id' => $competition->assessment_id,
                    'title' => null,
                    'order' => 1,
                    'starts_at' => null,
                    'ends_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_exams');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cấu hình hệ thống (mục 3.1, 18.8) — thay vì hard-code các ngưỡng nghiệp vụ
 * trong code (ví dụ RatingSummary::MIN_REVIEWS_TO_RANK), lưu vào bảng này để
 * Super Admin điều chỉnh được mà không cần release code (18.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            'key' => 'rating.min_reviews_to_rank',
            'value' => '5',
            'type' => 'integer',
            'label' => 'Ngưỡng số review tối thiểu để công bố xếp hạng',
            'description' => 'Số review tối thiểu một đối tượng (lớp/tài liệu) cần có trước khi rating_summary được công bố công khai (9.5, 18.8).',
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 23/10 (khách: "nút Lịch sử làm bài / Lịch sử làm đề, sang trang danh sách bài nộp như mẫu") — trang danh sách bài
 * nộp có cột Thời gian chạy / Bộ nhớ như các trang chấm bài (VNOI, LQDOJ). Trước đây máy chấm có trả 2 số này nhưng không
 * lưu lại, nên thêm 2 cột (null = lượt nộp cũ / câu không phải lập trình -> trang hiện "--"):
 *
 *   attempt_answers.run_time_ms    thời gian chạy LÂU NHẤT trong các test (ms)
 *   attempt_answers.run_memory_kb  bộ nhớ dùng NHIỀU NHẤT (KB)
 *
 * Thêm chỉ mục updated_at để trang danh sách (mới nhất trước, phân trang) không phải quét cả bảng.
 * Ghi ở Student\PracticeByQuestionService (bài tập chuyên đề). Chưa chạy migration thì mã vẫn chạy (AttemptAnswer::
 * supportsRunStats()), chỉ chưa có 2 cột số.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attempt_answers', 'run_time_ms')) {
            Schema::table('attempt_answers', function (Blueprint $table) {
                $table->unsignedInteger('run_time_ms')->nullable();
                $table->unsignedInteger('run_memory_kb')->nullable();
            });
        }

        if (! Schema::hasIndex('attempt_answers', 'attempt_answers_updated_at_index')) {
            Schema::table('attempt_answers', function (Blueprint $table) {
                $table->index('updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('attempt_answers', 'attempt_answers_updated_at_index')) {
            Schema::table('attempt_answers', function (Blueprint $table) {
                $table->dropIndex(['updated_at']);
            });
        }

        if (Schema::hasColumn('attempt_answers', 'run_time_ms')) {
            Schema::table('attempt_answers', function (Blueprint $table) {
                $table->dropColumn(['run_time_ms', 'run_memory_kb']);
            });
        }
    }
};

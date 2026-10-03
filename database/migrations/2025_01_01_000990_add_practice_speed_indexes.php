<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 3/10 (khách: "trang luyện tập mỗi lần click vào khách complain là chậm, sợ sau này nhiều
 * người dùng không ổn") — 2 CHỈ SỐ còn thiếu. Đây là nguyên nhân nặng nhất và là loại chậm
 * TĂNG DẦN theo lượng dữ liệu: càng nhiều lượt làm bài thì trang càng chậm thêm.
 *
 * ── 1. attempt_answers (question_id, verdict) ──────────────────────────────────────────────
 * Trang Luyện tập đếm số lượt nộp và số lượt đúng của 60 câu đang bày:
 *     SELECT question_id, COUNT(*), SUM(CASE WHEN verdict='accepted' OR score>0 ...)
 *     FROM attempt_answers WHERE question_id IN (...) GROUP BY question_id
 * Bảng này chỉ có unique(attempt_id, question_id). MySQL dùng chỉ số từ TRÁI sang, nên một
 * điều kiện chỉ có question_id KHÔNG dùng được chỉ số đó -> QUÉT TOÀN BẢNG mỗi lần mở trang.
 * Hôm nay bảng còn nhỏ nên chưa thấy gì; vài chục nghìn lượt nộp là thấy ngay.
 * Thêm verdict vào chỉ số để phép SUM(CASE...) đọc luôn trên chỉ số, khỏi mò về bảng.
 *
 * ── 2. attempts (assessment_id, submitted_at) ──────────────────────────────────────────────
 * Ô "Lượt làm" trên mỗi thẻ đề:
 *     SELECT assessment_id, COUNT(*) FROM attempts
 *     WHERE assessment_id IN (...) AND submitted_at IS NOT NULL GROUP BY assessment_id
 * Bảng có index(user_id, assessment_id) — cũng vướng đúng quy tắc trái-sang-phải ở trên nên
 * không dùng được, lại quét toàn bảng.
 *
 * KHÔNG đụng gì tới logic: chỉ số chỉ làm truy vấn chạy nhanh hơn, kết quả y hệt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->index(['question_id', 'verdict'], 'attempt_answers_question_verdict_index');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->index(['assessment_id', 'submitted_at'], 'attempts_assessment_submitted_index');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropIndex('attempt_answers_question_verdict_index');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->dropIndex('attempts_assessment_submitted_index');
        });
    }
};

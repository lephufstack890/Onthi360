<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 19/9 (7)(8) (khách: "nộp bài phần lập trình xong nó không hiển thị tỉ lệ AC") — LƯU SỐ
 * TEST ĐÃ QUA của bài lập trình, cho CẢ HAI đường chấm code của hệ thống:
 *   · attempt_coding_items — bài lập trình con trong đề PDF (PdfAttemptService)
 *   · attempt_answers      — câu Lập trình rời ở Luyện tập/Đề cấu trúc (PracticeByQuestionService)
 *
 * Gộp trong một migration để người quản trị chỉ phải chạy "php artisan migrate" một lần.
 *
 * Trước đây attempt_coding_items chỉ có verdict + score, tức là chỉ biết ĐÚNG hay SAI. Học sinh
 * nộp một chương trình qua 18/20 test cũng chỉ thấy đúng một chữ "Sai", không biết mình sai ở
 * mức nào — trong khi màn luyện tập theo câu thì hiện rõ "Đúng 4/20". Hai cột này lấp đúng
 * khoảng trống đó; máy chấm vốn đã trả về chi tiết từng test (CodeJudgingService::judge() ->
 * 'details'), chỉ là trước giờ đếm xong rồi bỏ đi.
 *
 * Nullable (không đặt default 0): bài nộp TRƯỚC khi có migration này chưa từng được đếm test,
 * để null thì màn kết quả biết là "không có số liệu" mà ẩn đi, thay vì hiện sai thành "0/0 test"
 * làm người xem tưởng bài không có test nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['attempt_coding_items', 'attempt_answers'] as $tableName) {
            // hasColumn: chạy lại migration trên máy đã thêm tay 2 cột này thì bỏ qua, không đổ lỗi.
            if (Schema::hasColumn($tableName, 'passed_tests')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('passed_tests')->nullable()->after('score');
                $table->unsignedInteger('total_tests')->nullable()->after('passed_tests');
            });
        }
    }

    public function down(): void
    {
        foreach (['attempt_coding_items', 'attempt_answers'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'passed_tests')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['passed_tests', 'total_tests']);
            });
        }
    }
};

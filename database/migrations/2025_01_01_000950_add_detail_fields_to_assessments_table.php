<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 2/10 (khách: "update lại UI trang luyện tập public ở tab đề thi luyện tập theo source
 * mới; khi update UI thấy field nào thiếu trong admin và giáo viên thì bổ sung") —
 * 6 cột mới cho đề thi, lấy đúng theo những gì bản mẫu
 * (education-main/src/components/ExamDetailPage.jsx + PracticePage.jsx) cần hiển thị mà cơ sở
 * dữ liệu CHƯA có chỗ lưu:
 *
 *   · subtitle         mô tả ngắn, in trên thẻ đề và ở khối "Thông tin đề thi" màn chi tiết.
 *                      Trước đây thẻ đề ghép tạm "N câu · X điểm · Y phút" vì không có mô tả.
 *   · author           Tác giả (bản mẫu: hàng "Tác giả" trong Thông tin đề thi).
 *   · province         Tỉnh/thành — lưu MÃ của App\Support\ProvinceCatalog (đã dựng 1/10 cho
 *                      kho câu hỏi), KHÔNG lưu nhãn tiếng Việt, để lọc/đổi nhãn về sau không
 *                      phải sửa dữ liệu.
 *   · academic_year    Năm học, dạng chuỗi "2024-2025" — CỐ Ý không dùng số nguyên như
 *                      questions.exam_year: năm học vắt qua 2 năm dương lịch.
 *   · exam_category    Loại đề để lọc ở dải chip ngoài trang (HSG/Chuyên/Olympic…), xem
 *                      App\Support\ExamCategory. KHÁC assessments.type (practice/assignment/
 *                      exam/competition_paper) — type là CÁCH DÙNG đề trong hệ thống, còn cột
 *                      này là KỲ THI mà đề mô phỏng; trộn 2 thứ vào một cột là hỏng cả hai.
 *   · cover_image_path Ảnh bìa đề. Trước đây thẻ đề lấy tạm ảnh sách theo số thứ tự
 *                      (asset('assets/book-img-N.jpg')) — tức là ảnh KHÔNG liên quan gì tới đề,
 *                      nhìn như đề có ảnh riêng mà thật ra không phải.
 *
 * Tất cả nullable: đề cũ để trống vẫn chạy, chỗ hiển thị tự rơi về "Chưa cập nhật" đúng như
 * bản mẫu làm. KHÔNG có lệnh backfill vì không suy được mấy thông tin này từ dữ liệu sẵn có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->string('author', 120)->nullable()->after('subtitle');
            $table->string('province', 20)->nullable()->after('author');
            $table->string('academic_year', 20)->nullable()->after('province');
            $table->string('exam_category', 30)->nullable()->after('academic_year');
            $table->string('cover_image_path')->nullable()->after('exam_category');

            $table->index(['exam_category', 'province']);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['exam_category', 'province']);
            $table->dropColumn(['subtitle', 'author', 'province', 'academic_year', 'exam_category', 'cover_image_path']);
        });
    }
};

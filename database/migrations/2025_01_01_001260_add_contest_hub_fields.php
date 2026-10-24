<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới, thiếu field nào thì bổ sung") — các cột cho phòng thi mới:
 *
 *  competitions
 *    description      mô tả ngắn hiện ở thẻ cuộc thi + phần "Thông tin cuộc thi & thể lệ"
 *    edition_label    nhãn mùa giải / đối tượng ("Mùa giải 2026", "Dành cho học sinh THCS")
 *    image_path       ảnh bìa (đĩa public); trống thì dùng ảnh mặc định
 *    learning_path_id lộ trình học liên quan (bộ lọc "Lộ trình" + link trong phòng thi)
 *    entry_password   mật khẩu tham gia KHẢO SÁT — lưu MÃ HOÁ (cast 'encrypted') để admin xem lại và gửi cho học sinh
 *
 *  competition_exams (kỳ thi / vòng)
 *    assignment_mode  random = hệ thống giao ngẫu nhiên mã đề | choice = học sinh tự chọn mã đề
 *    form_count       số mã đề hoán vị (Đề 101, 102, ...) — cùng câu, cùng điểm, chỉ đổi thứ tự câu; 1 = không hoán vị
 *
 *  competition_registrations
 *    source           student (tự đăng ký) | organizer (ban tổ chức thêm) | password (vào khảo sát bằng mật khẩu)
 *    added_by         ai thêm (khi source = organizer)
 *    exam_forms       mã đề đã giao cho học sinh theo từng kỳ: {"<competition_exam_id>": <số thứ tự mã đề>}
 *
 * Mọi chỗ đọc các cột này đều kiểm tra cột đã có chưa (Competition::supportsHubFields() …) — quên migrate thì
 * trang vẫn chạy như cũ, không lỗi 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competitions', 'entry_password')) {
            Schema::table('competitions', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
                $table->string('edition_label', 120)->nullable()->after('description');
                $table->string('image_path')->nullable()->after('edition_label');
                $table->foreignId('learning_path_id')->nullable()->after('image_path')
                    ->constrained('learning_paths')->nullOnDelete();
                $table->text('entry_password')->nullable()->after('learning_path_id');
            });
        }

        if (! Schema::hasColumn('competition_exams', 'form_count')) {
            Schema::table('competition_exams', function (Blueprint $table) {
                $table->string('assignment_mode', 10)->default('random')->after('order');
                $table->unsignedTinyInteger('form_count')->default(1)->after('assignment_mode');
            });
        }

        if (! Schema::hasColumn('competition_registrations', 'source')) {
            Schema::table('competition_registrations', function (Blueprint $table) {
                $table->string('source', 20)->nullable()->after('status');
                $table->foreignId('added_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
                $table->json('exam_forms')->nullable()->after('reject_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('competition_registrations', 'source')) {
            Schema::table('competition_registrations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('added_by');
                $table->dropColumn(['source', 'exam_forms']);
            });
        }

        if (Schema::hasColumn('competition_exams', 'form_count')) {
            Schema::table('competition_exams', function (Blueprint $table) {
                $table->dropColumn(['assignment_mode', 'form_count']);
            });
        }

        if (Schema::hasColumn('competitions', 'entry_password')) {
            Schema::table('competitions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('learning_path_id');
                $table->dropColumn(['description', 'edition_label', 'image_path', 'entry_password']);
            });
        }
    }
};

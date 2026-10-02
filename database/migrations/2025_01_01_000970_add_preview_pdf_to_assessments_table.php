<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 2/10 lần 3 (khách: "chỗ mở bản xem trước á chỉ cần hiển thị file pdf để xem thôi, trong
 * admin có 1 field nữa để up file pdf xem trước, bỏ 'Cho xem trước mấy câu đầu'") — BẢN XEM
 * TRƯỚC giờ là MỘT TỆP PDF do người ra đề tự tải lên.
 *
 * Cách này gọn và đúng ý người ra đề hơn hẳn 2 cách trước:
 *   · cắt trang từ đề gốc (preview_page_from/to): chỉ chạy được với đề PDF, mà đề Luyện tập thì
 *     luôn là dạng câu hỏi rời nên không bao giờ dùng được;
 *   · lấy đề bài n câu đầu: ghép từ nhiều nguồn, trình bày không do người ra đề kiểm soát.
 * Tải thẳng tệp PDF thì người ra đề muốn khoe phần nào là đúng phần đó.
 *
 * Tệp nằm ở disk 'local' (riêng tư, không nằm trong public/) — ra ngoài qua route
 * practice.exam.preview, giống hệt đường đi của assessments.pdf_path.
 *
 * Migration này cũng DỌN cột preview_item_count của lần sửa trước nếu nó đã kịp chạy — cách làm
 * đó bị bỏ, để lại cột thừa thì người sau đọc bảng sẽ tưởng còn dùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('preview_pdf_path')->nullable()->after('preview_page_to');
            $table->string('preview_pdf_original_name')->nullable()->after('preview_pdf_path');
        });

        if (Schema::hasColumn('assessments', 'preview_item_count')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropColumn('preview_item_count');
            });
        }
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['preview_pdf_path', 'preview_pdf_original_name']);
        });

        if (! Schema::hasColumn('assessments', 'preview_item_count')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->unsignedSmallInteger('preview_item_count')->nullable()->after('preview_page_to');
            });
        }
    }
};

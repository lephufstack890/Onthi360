<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 3/11 (khách: "có những đề có nhiều mã đề — bấm Bắt đầu làm bài thì hiện popup chọn mã đề rồi vào làm") — NHÓM MÃ ĐỀ.
 * Mỗi mã đề vẫn là MỘT ĐỀ RIÊNG đầy đủ như hiện nay (PDF, đáp án, chấm, nhật ký… giữ nguyên); chỉ thêm 2 cột:
 *   · variant_of_id : trỏ về ĐỀ GỐC (đề đại diện hiện ở danh sách). null = đề lẻ hoặc chính là đề gốc.
 *   · variant_code  : tên mã đề hiển thị (vd "101", "Mã A"). null = chưa đặt.
 * Mọi đề đang có để null cả 2 cột → giữ NGUYÊN mọi hành vi cũ. Xoá đề gốc thì các mã còn lại tự thành đề lẻ (nullOnDelete).
 * Chạy lại an toàn (đã có cột thì bỏ qua).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('assessments', 'variant_of_id')) {
            return;
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('variant_of_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('variant_code', 30)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessments', 'variant_of_id')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('variant_of_id');
                $table->dropColumn('variant_code');
            });
        }
    }
};

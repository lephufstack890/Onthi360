<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/11 (khách: "thêm phần up cả quyển sách, chỉ lưu lại, xem trước, in và tải PDF — ai đặt bản in thì mình chủ động in rồi
 * ship") — tệp PDF CẢ CUỐN của tài liệu, lưu NỘI BỘ (ổ private, học sinh không xem / tải được). Không đụng logic bán / đọc tài
 * liệu. Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'print_book_path')) {
            return;
        }
        Schema::table('products', function (Blueprint $table) {
            $table->string('print_book_path', 500)->nullable();
            $table->string('print_book_name', 255)->nullable();
            $table->unsignedBigInteger('print_book_size')->nullable();
            $table->timestamp('print_book_uploaded_at')->nullable();
            $table->unsignedBigInteger('print_book_uploaded_by')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'print_book_path')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['print_book_path', 'print_book_name', 'print_book_size', 'print_book_uploaded_at', 'print_book_uploaded_by']);
            });
        }
    }
};

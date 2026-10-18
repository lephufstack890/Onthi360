<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 18/10 — module BỘ ĐỀ (xem App\Services\ExamBundle\ExamBundleService — toàn bộ logic nằm ở đó).
 *
 * Khách chốt: "Bộ đề phải mua, đề trong Luyện tập vẫn làm miễn phí; thêm đề bên Luyện tập thì bộ đề tự gom, không phải
 * nhập lại". Bộ đề KHÔNG sao chép đề — chỉ TRỎ tới đề luyện tập đã có:
 *
 *   products.bundle_filters  JSON  bộ lọc tự gom đề: {categories[], levels[], provinces[], year_from, year_to}
 *   products.bundle_locked   bool  "Khoá danh sách": không tự thêm đề mới nữa (giữ đúng danh sách lúc khoá)
 *   assessments.bundle_only  bool  "Chỉ trong bộ đề": đề KHÔNG hiện ở trang Luyện tập, chỉ người mua bộ đề làm được
 *   product_assessments           đề admin ghim tay (source=manual), đề loại khỏi bộ (excluded=1), thứ tự tay (sort_order)
 *
 * Mọi cột mới đều có mặc định "như cũ" (null / false) nên chạy migration xong KHÔNG có gì thay đổi cho tới khi admin
 * thiết lập bộ đề. down() gỡ sạch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'bundle_filters')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('bundle_filters')->nullable();
                $table->boolean('bundle_locked')->default(false);
            });
        }

        if (! Schema::hasColumn('assessments', 'bundle_only')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->boolean('bundle_only')->default(false)->index();
            });
        }

        if (! Schema::hasTable('product_assessments')) {
            Schema::create('product_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
                // manual = admin ghim tay (luôn có trong bộ) · auto = dòng ghi nhớ cho đề do bộ lọc gom (thứ tự / bị loại)
                $table->string('source', 10)->default('manual');
                $table->boolean('excluded')->default(false);
                $table->unsignedInteger('sort_order')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'assessment_id']);
                $table->index('assessment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_assessments');

        if (Schema::hasColumn('assessments', 'bundle_only')) {
            if (Schema::hasIndex('assessments', ['bundle_only'])) {
                Schema::table('assessments', fn (Blueprint $table) => $table->dropIndex(['bundle_only']));
            }
            Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn('bundle_only'));
        }

        if (Schema::hasColumn('products', 'bundle_filters')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['bundle_filters', 'bundle_locked']);
            });
        }
    }
};

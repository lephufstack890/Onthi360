<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/11 (khách: "giáo viên + admin có thể thông báo cho học sinh và phụ huynh, hiển thị ra thông báo") — THÔNG BÁO
 * CHUNG của Quản trị (mục "Thông báo" ở menu trái admin). Khác thông báo lớp của giáo viên (class_announcements):
 *  · gửi được cho học sinh / phụ huynh / giáo viên, toàn hệ thống hoặc chỉ một số lớp;
 *  · ghim được lên thanh "Thông báo hệ thống" ở trang chủ.
 * Kèm 4 quyền khu quản trị admin.announcements.{view,create,update,delete} (App\Support\AdminPermissions).
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_announcements')) {
            Schema::create('system_announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 150)->nullable();
                $table->text('body')->nullable();
                $table->string('level', 20)->default('normal');      // normal | important
                $table->json('audiences')->nullable();               // ["students","parents","teachers"]
                $table->string('scope', 20)->default('all');         // all | classes
                $table->json('class_ids')->nullable();
                $table->boolean('pin_home')->default(false);         // hiện ở thanh "Thông báo hệ thống" trang chủ
                $table->timestamp('pin_until')->nullable();
                $table->string('state', 20)->default('draft');       // draft | published | recalled
                $table->unsignedInteger('student_count')->default(0);
                $table->unsignedInteger('parent_count')->default(0);
                $table->unsignedInteger('teacher_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamp('recalled_at')->nullable();
                $table->timestamps();

                $table->index(['state', 'published_at']);
                $table->index(['pin_home', 'state']);
            });
        }

        if (Schema::hasTable('permissions')) {
            $now = now();
            foreach (['view' => 'Xem', 'create' => 'Thêm', 'update' => 'Sửa', 'delete' => 'Xoá'] as $action => $label) {
                $slug = 'admin.announcements.'.$action;
                if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                    DB::table('permissions')->insert([
                        'slug' => $slug,
                        'label' => 'Thông báo — '.$label,
                        'group' => 'admin',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_announcements');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('slug', 'like', 'admin.announcements.%')->delete();
        }
    }
};

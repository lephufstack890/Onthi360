<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phân quyền THEO TỪNG NGƯỜI DÙNG (màn Admin > Người dùng > Chi tiết > tab "Phân quyền").
 *
 * permission_role vẫn là quyền MẶC ĐỊNH theo vai trò. Bảng này chỉ lưu phần CHÊNH LỆCH của riêng một người:
 *   effect = grant  → cấp thêm (ngoài mặc định của vai trò), kèm phạm vi (scope);
 *   effect = revoke → thu hồi một quyền mà vai trò vốn có.
 * Không có dòng nào = người đó dùng đúng mặc định của vai trò. Xem App\Models\User::hasPermission().
 *
 * Migration này cũng thêm 11 quyền chức năng của giáo viên vào bảng permissions (slug tiền tố "teacher.").
 * Mặc định theo vai trò của nhóm này tính ngay trong code (user có vai trò teacher => có), nên không cần
 * gán vào permission_role và không phụ thuộc seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('effect', 10)->default('grant'); // grant | revoke
            $table->string('scope', 20)->default('own');    // own | class | all
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        $now = now();
        $rows = [
            ['teacher.assign-practice', 'Giao bài', 'teacher_general'],
            ['teacher.assign-assessment', 'Giao đề', 'teacher_general'],
            ['teacher.assign-material', 'Giao tài liệu', 'teacher_general'],
            ['teacher.view-submissions', 'Xem lịch sử nộp bài', 'teacher_general'],
            ['teacher.sample-practice', 'Chỉ định bài mẫu', 'teacher_general'],
            ['teacher.organize-competition', 'Tổ chức cuộc thi', 'teacher_general'],
            ['teacher.question-create', 'Tạo câu hỏi', 'teacher_content'],
            ['teacher.question-edit', 'Sửa câu hỏi', 'teacher_content'],
            ['teacher.assessment-create', 'Tạo đề thi', 'teacher_content'],
            ['teacher.material-upload', 'Tải học liệu lên', 'teacher_content'],
            ['teacher.content-publish', 'Phát hành nội dung', 'teacher_content'],
        ];
        foreach ($rows as [$slug, $label, $group]) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert(['slug' => $slug, 'label' => $label, 'group' => $group, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        DB::table('permissions')->where('slug', 'like', 'teacher.%')->delete();
    }
};

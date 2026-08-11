<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Enums\QuestionType;
use App\Enums\Visibility;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Material;
use App\Models\Product;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu tối thiểu để dev local có ngay 1 luồng "sách -> lớp -> câu hỏi"
 * chạy được, tương ứng prototype #1 ở mục 14.5 của đặc tả BA. KHÔNG chạy ở môi
 * trường production (chỉ gọi seeder này qua `php artisan db:seed --class=DemoDataSeeder`
 * trong local/staging).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Demo', 'email' => 'admin@onthi360.test']);
        $admin->assignRole(Role::ADMIN);

        $teacher = User::factory()->create(['name' => 'Giáo viên Demo', 'email' => 'teacher@onthi360.test']);
        $teacher->assignRole(Role::TEACHER);
        TeacherProfile::create([
            'user_id' => $teacher->id,
            'approval_status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $student = User::factory()->create(['name' => 'Học sinh Demo', 'email' => 'student@onthi360.test']);
        $student->assignRole(Role::STUDENT);

        $bank = QuestionBank::create(['name' => 'Kho chung', 'owner_type' => 'shared']);

        $question = Question::create([
            'bank_id' => $bank->id,
            'code' => 'DEMO-001',
            'type' => QuestionType::Mcq,
            'title' => 'Câu hỏi trắc nghiệm mẫu',
            'body' => 'Đây là câu hỏi demo — 1 + 1 = ?',
            'points' => 10,
            'grading_config' => ['correct_options' => [1], 'options' => ['1', '2', '3', '4']],
            'status' => ContentStatus::Published,
            'created_by' => $teacher->id,
        ]);

        $product = Product::create([
            'type' => ProductType::Book,
            'title' => 'Sách Tin học 10 - Demo',
            'slug' => 'sach-tin-hoc-10-demo',
            'price' => 99000,
            'status' => ContentStatus::Published,
            'visibility' => Visibility::Private,
            'created_by' => $admin->id,
            'duration_months' => 12,
        ]);

        Material::create([
            'product_id' => $product->id,
            'type' => 'chapter',
            'title' => 'Chương 1 - Demo',
            'order' => 1,
            'status' => ContentStatus::Published,
        ]);

        $course = Course::create([
            'title' => 'Luyện thi vào 10 Chuyên Tin - Demo',
            'slug' => 'luyen-thi-vao-10-chuyen-tin-demo',
            'status' => ContentStatus::Published,
            'created_by' => $teacher->id,
        ]);

        $classRoom = ClassRoom::create([
            'course_id' => $course->id,
            'code' => '10CT-DEMO',
            'name' => '10CT - Demo',
            'status' => 'active',
        ]);

        $classRoom->teachers()->attach($teacher->id, ['role' => 'main']);
        $classRoom->enrollments()->create(['student_id' => $student->id]);

        $this->command?->info('Demo data đã tạo: admin@onthi360.test / teacher@onthi360.test / student@onthi360.test (password mặc định của factory).');
    }
}

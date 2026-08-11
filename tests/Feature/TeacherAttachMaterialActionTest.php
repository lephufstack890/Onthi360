<?php

namespace Tests\Feature;

use App\Actions\TeacherAttachMaterialAction;
use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Product;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Test bảng 7.2 — "Điều kiện khi giáo viên thêm học liệu vào lớp". */
class TeacherAttachMaterialActionTest extends TestCase
{
    use RefreshDatabase;

    private TeacherAttachMaterialAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new TeacherAttachMaterialAction;
    }

    public function test_unapproved_teacher_is_blocked(): void
    {
        $teacher = User::factory()->create();
        [$material, $classRoom] = $this->materialAndClassFor($teacher);

        $decision = $this->action->authorize($teacher, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('teacher_not_approved', $decision->primaryReasonCode);
    }

    public function test_approved_teacher_without_teaching_right_is_blocked(): void
    {
        $teacher = $this->approvedTeacher();
        [$material, $classRoom] = $this->materialAndClassFor($teacher);

        $decision = $this->action->authorize($teacher, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_teaching_right', $decision->primaryReasonCode);
    }

    public function test_expired_teaching_right_is_blocked(): void
    {
        $teacher = $this->approvedTeacher();
        [$material, $classRoom] = $this->materialAndClassFor($teacher);
        $this->grantTeachingRight($teacher, $material->product, expiresAt: now()->subDay());

        $decision = $this->action->authorize($teacher, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('teaching_right_expired', $decision->primaryReasonCode);
    }

    public function test_teacher_not_in_charge_of_class_is_blocked(): void
    {
        $teacher = $this->approvedTeacher();
        $product = Product::factory()->create();
        $material = Material::factory()->for($product)->create();
        $classRoom = ClassRoom::factory()->create(); // không gắn teacher này
        $this->grantTeachingRight($teacher, $product, expiresAt: now()->addYear());

        $decision = $this->action->authorize($teacher, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('not_class_teacher', $decision->primaryReasonCode);
    }

    public function test_teacher_can_attach_material_to_multiple_classes_with_one_teaching_right(): void
    {
        $teacher = $this->approvedTeacher();
        $product = Product::factory()->create();
        $material = Material::factory()->for($product)->create();
        $this->grantTeachingRight($teacher, $product, expiresAt: now()->addYear());

        $classA = ClassRoom::factory()->create();
        $classA->teachers()->attach($teacher->id, ['role' => 'main']);
        $classB = ClassRoom::factory()->create();
        $classB->teachers()->attach($teacher->id, ['role' => 'main']);

        $linkA = $this->action->execute($teacher, $material, $classA);
        $linkB = $this->action->execute($teacher, $material, $classB);

        $this->assertTrue($linkA->isUsable());
        $this->assertTrue($linkB->isUsable());
        // Không sao chép câu hỏi — cùng 1 material_id được tham chiếu ở cả 2 lớp (2.2 mục 3).
        $this->assertSame($material->id, $linkA->material_id);
        $this->assertSame($material->id, $linkB->material_id);
    }

    /** @return array{0: Material, 1: ClassRoom} */
    private function materialAndClassFor(User $teacher): array
    {
        $product = Product::factory()->create();
        $material = Material::factory()->for($product)->create();
        $classRoom = ClassRoom::factory()->create();
        $classRoom->teachers()->attach($teacher->id, ['role' => 'main']);

        return [$material, $classRoom];
    }

    private function approvedTeacher(): User
    {
        $teacher = User::factory()->create();
        TeacherProfile::create(['user_id' => $teacher->id, 'approval_status' => 'approved']);

        return $teacher->refresh();
    }

    private function grantTeachingRight(User $teacher, Product $product, \DateTimeInterface $expiresAt): void
    {
        $teacher->accessRights()->create([
            'product_id' => $product->id,
            'scope' => AccessScope::TeacherTeaching,
            'status' => AccessRightStatus::Active,
            'starts_at' => now()->subDay(),
            'expires_at' => $expiresAt,
            'class_limit' => null,
            'source' => 'order',
        ]);
    }
}

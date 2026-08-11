<?php

namespace Tests\Feature;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ClassMaterialStatus;
use App\Enums\ProgressUnitType;
use App\Enums\Visibility;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use App\Services\AccessGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test cho App\Services\AccessGateService — cài đúng 3 cửa độc lập của 7.3.
 * Đây là logic quan trọng nhất trong toàn hệ thống (sai -> lộ nội dung riêng
 * tư), nên test từng nhánh quyết định riêng biệt.
 */
class AccessGateServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccessGateService $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new AccessGateService;
    }

    public function test_public_material_is_always_allowed(): void
    {
        $product = Product::factory()->public()->create();
        $material = Material::factory()->for($product)->create();
        $student = User::factory()->create();

        $decision = $this->gate->canAccessMaterial($student, $material);

        $this->assertTrue($decision->allowed);
    }

    public function test_private_material_without_personal_access_is_denied(): void
    {
        $product = Product::factory()->create(['visibility' => Visibility::Private]);
        $material = Material::factory()->for($product)->create();
        $student = User::factory()->create();

        $decision = $this->gate->canAccessMaterial($student, $material);

        $this->assertFalse($decision->allowed);
        $this->assertSame('need_personal_access', $decision->primaryReasonCode);
    }

    public function test_class_member_with_access_but_teacher_has_not_opened_progress_is_denied(): void
    {
        [$student, $classRoom, $material] = $this->setupClassWithMaterial();
        $this->grantPersonalAccess($student, $material->product);

        $decision = $this->gate->canAccessMaterial($student, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('teacher_not_opened', $decision->primaryReasonCode);
    }

    public function test_class_member_with_access_and_opened_progress_is_allowed(): void
    {
        [$student, $classRoom, $material] = $this->setupClassWithMaterial();
        $this->grantPersonalAccess($student, $material->product);
        $classRoom->progressUnlocks()->create([
            'unit_type' => ProgressUnitType::Chapter,
            'unit_id' => $material->id,
            'opened_by' => $classRoom->teachers()->first()->id,
            'opened_at' => now(),
        ]);

        $decision = $this->gate->canAccessMaterial($student, $material, $classRoom);

        $this->assertTrue($decision->allowed);
    }

    public function test_class_member_missing_both_gates_reports_both_reasons(): void
    {
        [$student, $classRoom, $material] = $this->setupClassWithMaterial();

        $decision = $this->gate->canAccessMaterial($student, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('need_personal_access', $decision->primaryReasonCode);
        $this->assertContains('teacher_not_opened', $decision->missingGates);
    }

    public function test_student_not_enrolled_in_class_is_denied_regardless_of_access(): void
    {
        [$student, $classRoom, $material] = $this->setupClassWithMaterial(enroll: false);
        $this->grantPersonalAccess($student, $material->product);

        $decision = $this->gate->canAccessMaterial($student, $material, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('not_in_class_path', $decision->primaryReasonCode);
    }

    /** @return array{0: User, 1: ClassRoom, 2: Material} */
    private function setupClassWithMaterial(bool $enroll = true): array
    {
        $teacher = User::factory()->create();
        $product = Product::factory()->create(['visibility' => Visibility::Private]);
        $material = Material::factory()->for($product)->create();
        $classRoom = ClassRoom::factory()->create();
        $classRoom->teachers()->attach($teacher->id, ['role' => 'main']);

        $student = User::factory()->create();
        if ($enroll) {
            $classRoom->enrollments()->create(['student_id' => $student->id]);
        }

        $classRoom->classMaterials()->create([
            'material_id' => $material->id,
            'product_id' => $product->id,
            'status' => ClassMaterialStatus::Active,
            'added_by' => $teacher->id,
            'added_at' => now(),
        ]);

        return [$student, $classRoom, $material];
    }

    private function grantPersonalAccess(User $user, Product $product): void
    {
        $user->accessRights()->create([
            'product_id' => $product->id,
            'scope' => AccessScope::PersonalLearning,
            'status' => AccessRightStatus::Active,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'source' => 'order',
        ]);
    }
}

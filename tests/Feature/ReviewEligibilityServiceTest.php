<?php

namespace Tests\Feature;

use App\Enums\AccessScope;
use App\Models\ClassRoom;
use App\Models\Product;
use App\Models\User;
use App\Services\ReviewEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Test 9.2 "Ai được đánh giá và khi nào". */
class ReviewEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReviewEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReviewEligibilityService;
    }

    public function test_student_without_entitlement_cannot_review_material(): void
    {
        $product = Product::factory()->create();
        $student = User::factory()->create();

        $decision = $this->service->eligibleForMaterialReview($student, $product);

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_entitlement', $decision->primaryReasonCode);
    }

    public function test_student_with_entitlement_but_not_opened_cannot_review_material(): void
    {
        $product = Product::factory()->create();
        $student = User::factory()->create();
        $student->accessRights()->create([
            'product_id' => $product->id,
            'scope' => AccessScope::PersonalLearning,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'source' => 'order',
        ]);

        $decision = $this->service->eligibleForMaterialReview($student, $product);

        $this->assertFalse($decision->allowed);
        $this->assertSame('not_opened_yet', $decision->primaryReasonCode);
    }

    public function test_teacher_cannot_review_own_class(): void
    {
        $teacher = User::factory()->create();
        $classRoom = ClassRoom::factory()->create();
        $classRoom->teachers()->attach($teacher->id, ['role' => 'main']);

        $decision = $this->service->eligibleForClassReview($teacher, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('teacher_cannot_review_own_class', $decision->primaryReasonCode);
    }

    public function test_student_below_attendance_threshold_cannot_review_class(): void
    {
        $classRoom = ClassRoom::factory()->create();
        $student = User::factory()->create();
        $classRoom->enrollments()->create(['student_id' => $student->id]);

        $decision = $this->service->eligibleForClassReview($student, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('threshold_not_met', $decision->primaryReasonCode);
    }

    public function test_non_member_cannot_review_class(): void
    {
        $classRoom = ClassRoom::factory()->create();
        $outsider = User::factory()->create();

        $decision = $this->service->eligibleForClassReview($outsider, $classRoom);

        $this->assertFalse($decision->allowed);
        $this->assertSame('not_a_member', $decision->primaryReasonCode);
    }
}

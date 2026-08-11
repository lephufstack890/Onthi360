<?php

namespace Tests\Feature;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Models\ActivationCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\OrderActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Test vòng đời 7.4: đơn -> duyệt -> mã -> kích hoạt -> quyền. */
class OrderActivationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderActivationService;
    }

    public function test_approving_offline_order_generates_unused_codes_and_no_access_right_yet(): void
    {
        $product = Product::factory()->create(['duration_months' => 6]);
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'quantity' => 2]);
        $admin = User::factory()->create();

        $this->service->approveOfflineOrder($order->refresh(), $admin);

        $this->assertSame(2, ActivationCode::where('product_id', $product->id)->count());
        $this->assertSame(0, \App\Models\AccessRight::count(), 'Chưa kích hoạt thì chưa được cấp quyền.');
    }

    public function test_activating_code_creates_access_right_starting_now_not_at_order_time(): void
    {
        $product = Product::factory()->create(['duration_months' => 6]);
        $code = ActivationCode::factory()->create([
            'product_id' => $product->id,
            'scope' => AccessScope::PersonalLearning,
            'validity_months' => 6,
        ]);
        $student = User::factory()->create();

        $accessRight = $this->service->activate($code, $student);

        $this->assertTrue($accessRight->isCurrentlyActive());
        $this->assertSame(
            $accessRight->starts_at->addMonths(6)->toDateString(),
            $accessRight->expires_at->toDateString(),
        );
        $this->assertSame(ActivationCodeStatus::Activated, $code->refresh()->status);
    }

    public function test_code_cannot_be_activated_twice(): void
    {
        $code = ActivationCode::factory()->create(['scope' => AccessScope::PersonalLearning]);
        $student = User::factory()->create();
        $this->service->activate($code, $student);

        $decision = $this->service->canActivate($code->refresh(), User::factory()->create());

        $this->assertFalse($decision->allowed);
        $this->assertSame('code_not_usable', $decision->primaryReasonCode);
    }

    public function test_teacher_teaching_code_requires_approved_teacher_and_is_never_reinterpreted(): void
    {
        $code = ActivationCode::factory()->create(['scope' => AccessScope::TeacherTeaching]);
        $student = User::factory()->create(); // không phải giáo viên đã duyệt

        $decision = $this->service->canActivate($code, $student);

        $this->assertFalse($decision->allowed);
        $this->assertSame('must_be_approved_teacher', $decision->primaryReasonCode);

        $teacher = User::factory()->create();
        TeacherProfile::create(['user_id' => $teacher->id, 'approval_status' => 'approved']);
        $accessRight = $this->service->activate($code, $teacher->refresh());

        // Mã quyền dạy PHẢI cấp đúng scope teacher_teaching, không tự đổi thành personal_learning.
        $this->assertSame(AccessScope::TeacherTeaching, $accessRight->scope);
        $this->assertNull($accessRight->class_limit);
    }
}

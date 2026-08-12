<?php

namespace App\Http\Controllers\Access;

use App\Enums\AccessRightStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccessController extends Controller
{
    /** access.checkout (ACC-03) — 7.4/7.5: checkout theo scope, sách mềm bắt buộc. */
    public function checkout(Request $request, int $product): View
    {
        $user = Auth::user();
        $productModel = Product::findOrFail($product);

        // Chỉ giáo viên đã được duyệt mới thấy scope "Dùng để dạy" (7.2, 7.5).
        $canTeach = $user->isTeacherApproved();

        return view('access.checkout', [
            'product' => $productModel,
            'canTeach' => $canTeach,
            'printPrice' => 50000, // TODO: giá bản in thật cần cấu hình riêng, chưa có trường trong schema.
        ]);
    }

    /**
     * access.activate (ACC-02).
     * TODO: xử lý submit qua App\Services\OrderActivationService::activate() khi service này
     * được xây; hiện chỉ render form, chưa có logic kích hoạt thật.
     */
    public function activate(Request $request): View
    {
        return view('access.activate');
    }

    /** access.myAccess (ACC-07) — 7.3: Đang có quyền / Sắp hết hạn / Đã hết hạn. */
    public function myAccess(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'active');

        $all = $user->accessRights()->with('product')->get();
        $now = now();

        $classify = function ($right) use ($now) {
            if ($right->status !== AccessRightStatus::Active || $right->expires_at === null) {
                return $right->status === AccessRightStatus::Expired ? 'expired' : 'other';
            }

            return $right->expires_at->diffInDays($now, false) >= -14 && $right->expires_at->isFuture()
                ? 'expiring'
                : ($right->expires_at->isPast() ? 'expired' : 'active');
        };

        $grouped = $all->groupBy($classify);

        $tabs = [
            ['label' => 'Đang có quyền', 'href' => route('access.myAccess'), 'active' => $tab === 'active', 'count' => $grouped->get('active', collect())->count()],
            ['label' => 'Sắp hết hạn', 'href' => route('access.myAccess', ['tab' => 'expiring']), 'active' => $tab === 'expiring', 'count' => $grouped->get('expiring', collect())->count()],
            ['label' => 'Đã hết hạn', 'href' => route('access.myAccess', ['tab' => 'expired']), 'active' => $tab === 'expired', 'count' => $grouped->get('expired', collect())->count()],
        ];

        $rights = $grouped->get($tab === 'active' ? 'active' : $tab, collect())->map(fn ($r) => [
            'productId' => $r->product_id,
            'title' => $r->product->title ?? 'Học liệu',
            'expires' => $r->expires_at?->format('d/m/Y') ?? 'Không xác định',
            'status' => match (true) {
                $tab === 'expiring' => 'Sắp hết hạn',
                $tab === 'expired' => 'Đã hết hạn',
                default => 'Còn hiệu lực',
            },
            'tone' => match ($tab) {
                'expiring' => 'warning',
                'expired' => 'neutral',
                default => 'success',
            },
        ])->values()->all();

        return view('access.my-access', ['tab' => $tab, 'tabs' => $tabs, 'rights' => $rights]);
    }

    /**
     * access.blocked (ACC-08) — 7.3: 3 cửa Thành viên/lớp, Quyền cá nhân, Tiến độ chung.
     * TODO: nối App\Services\AccessGateService thật để tính từng App\Support\AccessDecision;
     * hiện trả về 3 cửa mặc định "đã qua" vì chưa có service, tránh hiển thị lý do sai.
     */
    public function blocked(Request $request, int $material): View
    {
        $gates = [
            ['label' => 'Thành viên/lớp', 'passed' => true, 'message' => 'Đang chờ App\\Services\\AccessGateService.'],
            ['label' => 'Quyền học cá nhân', 'passed' => true, 'message' => 'Đang chờ App\\Services\\AccessGateService.'],
            ['label' => 'Tiến độ chung', 'passed' => true, 'message' => 'Đang chờ App\\Services\\AccessGateService.'],
        ];

        return view('access.blocked', ['gates' => $gates, 'materialId' => $material]);
    }
}

<?php

namespace App\Http\Controllers\Access;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessController extends Controller
{
    public function __construct(private AccessService $accessService) {}

    /** access.checkout (ACC-03) — 7.4/7.5: checkout theo scope, sách mềm bắt buộc. */
    public function checkout(Request $request, int $product): View
    {
        $user = Auth::user();

        return view('access.checkout', $this->accessService->checkoutData($user, $product));
    }

    /**
     * access.checkout.store (25/8, SỬA 25/8 (2)) — nút "Đặt đơn" tạo Order thật (xem
     * AccessService::placeOrder()). SỬA 25/8 (2):
     * - Thêm payment_method thật (offline/token) — trước chỉ có offline cứng.
     * - "đặt xong thì chuyển qua trang tài liệu luôn": thành công KHÔNG quay lại trang đặt đơn
     *   nữa mà chuyển thẳng sang materials.show (mục lục đã mở khoá ngay nếu trả bằng token).
     * - Thiếu token (lỗi riêng key 'insufficient_token' từ placeOrder()): chuyển hẳn sang trang
     *   Ví (wallet.index) để nạp thêm — không quay lại trang đặt đơn như các lỗi validate khác.
     * - Lỗi validate khác (scope chưa đủ điều kiện, đã có quyền/đơn đang xử lý...) vẫn quay lại
     *   đúng trang checkout để sửa, không văng ra trang lỗi.
     */
    public function store(Request $request, int $product): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'scope' => ['required', 'string', 'in:personal_learning,teacher_teaching'],
            'include_print' => ['nullable', 'boolean'],
            'payment_method' => ['required', 'string', 'in:offline,token'],
        ]);

        try {
            $order = $this->accessService->placeOrder($user, $product, $data);
        } catch (ValidationException $e) {
            if (array_key_exists('insufficient_token', $e->errors())) {
                return redirect()->route('wallet.index')->with('status', 'need-topup');
            }

            return redirect()->route('access.checkout', $product)->withErrors($e->errors());
        }

        return redirect()->route('materials.show', $product)
            ->with('status', $order->status === OrderStatus::Completed ? 'access-granted' : 'order-placed')
            ->with('orderNo', $order->order_no);
    }

    /**
     * access.history (mới 25/8 (2)) — "lưu lại lịch sử đặt mua có học sinh luôn": liệt kê Order
     * thô của CHÍNH user đang đăng nhập, khác access.myAccess (chỉ hiện AccessRight).
     */
    public function history(Request $request): View
    {
        $user = Auth::user();

        return view('access.history', $this->accessService->purchaseHistoryData($user));
    }

    /**
     * access.activate (ACC-02).
     */
    public function activate(Request $request): View
    {
        $user = Auth::user();
        $code = $request->query('code');

        return view('access.activate', $this->accessService->activationLookup($user, $code));
    }

    /**
     * access.activate.store (25/8) — SỬA: form "Kích hoạt" giờ submit thật (trước đây chưa nối
     * gì cả, xem ghi chú cũ ở AccessService::activateCode()). Lỗi (mã không tồn tại/sai
     * phạm vi/đã dùng) quay lại đúng trang kích hoạt, giữ nguyên mã đã gõ qua old('code').
     */
    public function activateStore(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);

        try {
            $this->accessService->activateCode($user, $data['code']);
        } catch (ValidationException $e) {
            return redirect()->route('access.activate')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('access.myAccess')->with('status', 'code-activated');
    }

    /** access.myAccess (ACC-07) — 7.3: Đang có quyền / Sắp hết hạn / Đã hết hạn. */
    public function myAccess(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'active');

        return view('access.my-access', $this->accessService->myAccessData($user, $tab));
    }

    public function blocked(Request $request, int $material): View
    {
        $user = Auth::user();
        $classRoomId = $request->query('class') ? (int) $request->query('class') : null;

        return view('access.blocked', $this->accessService->blockedGates($user, $material, $classRoomId));
    }

    /**
     * access.resource (mới 27/8, "4 file đính kèm sản phẩm") — tải/xem PDF hướng dẫn/ZIP bài
     * tập/học liệu media của 1 sản phẩm. {kind} giới hạn bằng route whereIn (xem routes/web.php)
     * — logic quyền/tìm file THẬT nằm hết ở AccessService::downloadResource().
     */
    public function resource(Request $request, int $product, string $kind): StreamedResponse
    {
        return $this->accessService->downloadResource(Auth::user(), $product, $kind);
    }
}

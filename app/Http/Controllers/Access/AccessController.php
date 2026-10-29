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

    public function checkout(Request $request, int $product): View|RedirectResponse
    {
        $user = Auth::user();
        $data = $this->accessService->checkoutData($user, $product);

        if ($this->accessService->nothingLeftToBuy($user, $data['ownedScopes'])) {
            return $data['isCourse'] && $data['courseId'] !== null
                ? redirect()->route('access.chooseClass', $data['courseId'])->with('status', 'already-owned')
                : redirect()->route('materials.show', $product)->with('status', 'already-owned');
        }

        return view('access.checkout', $data);
    }

    public function store(Request $request, int $product): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'scope' => ['required', 'string', 'in:personal_learning,teacher_teaching,auto'],
            'include_print' => ['nullable', 'boolean'],
            'payment_method' => ['required', 'string', 'in:token'],
        ]);

        if ($data['scope'] === 'auto') {
            $data['scope'] = $user->isTeacherApproved() ? 'teacher_teaching' : 'personal_learning';
        }

        try {
            $order = $this->accessService->placeOrder($user, $product, $data);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                $needTopup = array_key_exists('insufficient_token', $e->errors());

                return response()->json([
                    'ok' => false,
                    'needTopup' => $needTopup,
                    'walletHref' => route('wallet.index'),
                    'message' => collect($e->errors())->flatten()->first() ?: 'Chưa mua được — vui lòng thử lại.',
                ], 422);
            }
            if (array_key_exists('insufficient_token', $e->errors())) {
                return redirect()->route('wallet.index')->with('status', 'need-topup');
            }

            return redirect()->route('access.checkout', $product)->withErrors($e->errors());
        }

        // SỬA 28/10 — mua ngay trong hộp mua: trừ token + có quyền ngay, trả JSON để hộp đóng lại và báo "Mua thành công".
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $order->status === OrderStatus::Completed,
                'orderNo' => $order->order_no,
                'paid' => \App\Support\TokenPrice::label((int) $order->total_amount),
                'balance' => \App\Support\TokenPrice::label((int) $user->fresh()->token_balance),
                'message' => $order->status === OrderStatus::Completed ? 'Mua thành công' : 'Đã tạo đơn, chờ duyệt',
            ]);
        }

        $status = $order->status === OrderStatus::Completed ? 'access-granted' : 'order-placed';
        $courseId = $this->accessService->courseIdForProduct($product);

        if ($request->input('quay_lai') === 'bo-de') {
            return redirect()->route('bundles.show', $product)->with('status', $status);
        }

        if ($courseId !== null) {
            return redirect()->route('access.chooseClass', $courseId)
                ->with('status', $status)
                ->with('orderNo', $order->order_no);
        }

        return redirect()->route('materials.show', $product)
            ->with('status', $status)
            ->with('orderNo', $order->order_no);
    }

    public function history(Request $request): View
    {
        $user = Auth::user();

        return view('access.history', $this->accessService->purchaseHistoryData($user));
    }

    public function activate(Request $request): View
    {
        $user = Auth::user();
        $code = $request->query('code');

        return view('access.activate', $this->accessService->activationLookup($user, $code));
    }

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

    public function resource(Request $request, int $product, string $kind): StreamedResponse
    {
        return $this->accessService->downloadResource(Auth::user(), $product, $kind);
    }

    public function exerciseAttachment(Request $request, int $product, int $exercise, string $kind): StreamedResponse
    {
        return $this->accessService->downloadExerciseAttachment(Auth::user(), $product, $exercise, $kind);
    }
}

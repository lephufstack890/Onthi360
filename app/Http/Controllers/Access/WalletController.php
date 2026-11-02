<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $pendingTopup = $this->walletService->latestPendingFor($user);

        return view('access.wallet', [
            'balance' => $this->walletService->balanceFor($user),
            'history' => $this->walletService->historyFor($user),
            'bankInfo' => $this->walletService->bankInfo(),
            'pendingTopup' => $pendingTopup,
            'pendingQrUrl' => $pendingTopup !== null ? $this->walletService->qrUrl($pendingTopup) : null,
        ]);
    }

    public function qrImage(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // SỬA 3/11 — ảnh đã cắt lấy thẻ QR (bỏ khung nền xung quanh); không cắt được thì là ảnh gốc.
        $path = $this->walletService->qrDisplayPath();
        abort_if($path === null, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path, 'qr-ngan-hang.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function requestTopup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // SỬA 2/11 — tối đa 500.000đ / lần (WalletService::MAX_TOPUP_AMOUNT).
            'amount' => ['required', 'integer', 'min:10000', 'max:'.\App\Services\WalletService::MAX_TOPUP_AMOUNT],
        ], [
            'amount.required' => 'Nhập số tiền muốn nạp.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền nạp tối thiểu là 10.000đ.',
            'amount.max' => 'Mỗi lần nạp tối đa 500.000đ.',
        ]);

        try {
            $this->walletService->requestTopup(Auth::user(), (int) $data['amount']);
        } catch (ValidationException $e) {
            return redirect()->route('wallet.index')->withErrors($e->errors());
        }

        return redirect()->route('wallet.index')->with('status', 'topup-requested');
    }
}

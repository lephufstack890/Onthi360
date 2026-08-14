<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * wallet.* — "Nộp tiền thành token" + QR ngân hàng (note họp 13/8, mục 7-8): nạp tiền một
 * lần thành số dư token dùng chung cho nhiều lần thanh toán sau này, thay vì phải chuyển
 * khoản riêng lẻ theo từng đơn.
 */
class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    /** wallet.index — số dư, lịch sử nạp, và QR/mã chuyển khoản nếu còn 1 yêu cầu đang chờ duyệt. */
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

    /** wallet.request — tạo yêu cầu nạp token, sinh mã chuyển khoản riêng để đối soát (7.4). */
    public function requestTopup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:50000000'],
        ]);

        try {
            $this->walletService->requestTopup(Auth::user(), (int) $data['amount']);
        } catch (ValidationException $e) {
            return redirect()->route('wallet.index')->withErrors($e->errors());
        }

        return redirect()->route('wallet.index')->with('status', 'topup-requested');
    }
}

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
        $path = $this->walletService->qrImagePath();
        abort_if($path === null, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path, 'qr-ngan-hang.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

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

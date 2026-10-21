<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settingsService) {}

    public function index(Request $request): View
    {
        return view('admin.settings.index', $this->settingsService->indexData());
    }

    /** admin.settings.rating-threshold.update — 18.8: ngưỡng review thành config, không hard-code. */
    public function updateRatingThreshold(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'min_reviews_to_rank' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $this->settingsService->updateRatingThreshold($request->user(), (int) $data['min_reviews_to_rank']);

        return redirect()->route('admin.settings.index')->with('status', 'settings-rating-threshold-updated');
    }

    public function updateWalletBankInfo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_account_no' => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:150'],
            // SỬA 21/10 (khách: "thay Mã BIN bằng chọn hình ảnh QR ngân hàng") — ảnh QR nhận tiền tải từ app ngân hàng.
            'bank_qr' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_bank_qr' => ['nullable', 'boolean'],
        ], [], ['bank_qr' => 'Ảnh QR ngân hàng']);

        $this->settingsService->updateWalletBankInfo(
            $request->user(),
            $data,
            $request->file('bank_qr'),
            $request->boolean('remove_bank_qr'),
        );

        return redirect()->route('admin.settings.index')->with('status', 'settings-wallet-bank-updated');
    }
}

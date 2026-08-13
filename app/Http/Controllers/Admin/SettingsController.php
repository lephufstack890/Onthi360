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

    /**
     * admin.settings.index — 3.1: Super Admin cấu hình role, chính sách, tích hợp.
     * Route bị chặn bởi middleware role:super_admin (routes/web.php) — Admin
     * thường không có "Cấu hình hệ thống tối cao" theo BA.
     */
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
}

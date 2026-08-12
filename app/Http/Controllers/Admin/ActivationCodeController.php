<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivationCodeStatus;
use App\Enums\AccessScope;
use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivationCodeController extends Controller
{
    /** admin.activation-codes.index — 7.4: mã sai scope không tự chuyển đổi. */
    public function index(Request $request): View
    {
        $codes = ActivationCode::with('orderItem.order')->latest()->limit(50)->get()->map(fn ($c) => [
            'code' => $c->code,
            'order' => $c->orderItem->order->id ?? null,
            'scope' => $c->scope === AccessScope::TeacherTeaching ? 'Dùng để dạy' : 'Học cá nhân',
            'status' => match ($c->status) {
                ActivationCodeStatus::Unused => 'Chưa dùng',
                ActivationCodeStatus::Activated => 'Đã dùng',
                ActivationCodeStatus::Revoked => 'Đã thu hồi',
            },
            'tone' => match ($c->status) {
                ActivationCodeStatus::Unused => 'neutral',
                ActivationCodeStatus::Activated => 'success',
                ActivationCodeStatus::Revoked => 'danger',
            },
        ])->all();

        return view('admin.activation-codes.index', ['codes' => $codes]);
    }
}

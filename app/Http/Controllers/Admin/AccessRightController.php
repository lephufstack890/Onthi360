<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Http\Controllers\Controller;
use App\Models\AccessRight;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessRightController extends Controller
{
    /** admin.access-rights.index — 7.1-7.5: quyền học cá nhân / quyền dạy đa lớp. */
    public function index(Request $request): View
    {
        $tabs = [
            ['label' => 'Sản phẩm', 'href' => route('admin.products.index'), 'active' => false, 'count' => Product::count()],
            ['label' => 'Quyền đã cấp', 'href' => route('admin.access-rights.index'), 'active' => true, 'count' => AccessRight::count()],
        ];

        $rights = AccessRight::with('user', 'product')->latest()->limit(50)->get()->map(function ($r) {
            $status = match (true) {
                $r->status === AccessRightStatus::Active && $r->expires_at?->isFuture() && $r->expires_at->diffInDays(now()) <= 14 => ['Sắp hết hạn', 'warning'],
                $r->status === AccessRightStatus::Active => ['Hiệu lực', 'success'],
                $r->status === AccessRightStatus::Expired => ['Hết hạn', 'danger'],
                default => [(string) $r->status->value, 'neutral'],
            };

            return [
                'id' => $r->id,
                'user' => $r->user->name ?? '',
                'product' => $r->product->title ?? '',
                'scope' => $r->scope === AccessScope::TeacherTeaching ? 'Dùng để dạy (mọi lớp phụ trách)' : 'Học cá nhân',
                'expires' => $r->expires_at?->format('d/m/Y') ?? 'Không xác định',
                'status' => $status[0],
                'tone' => $status[1],
            ];
        })->all();

        return view('admin.access-rights.index', ['tabs' => $tabs, 'rights' => $rights]);
    }
}

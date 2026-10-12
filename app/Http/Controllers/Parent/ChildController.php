<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use App\Services\Parent\ParentWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function __construct(
        private ChildService $childService,
        private ParentWorkspaceService $workspace,
    ) {}

    /** "Con của tôi" — hồ sơ từng con + tiến độ + hoạt động + quản lý liên kết. */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('parent.children.index', $this->workspace->snapshot($user, $request->query('child'))
            + ['links' => $this->childService->listForParent($user)['children']]);
    }

    public function storeLinkRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $this->childService->requestLink($request->user(), $data['student_email']);
        } catch (ValidationException $e) {
            return redirect()->route('parent.children.index')->withErrors($e->errors());
        }

        return redirect()->route('parent.children.index')->with('status', 'link-requested');
    }

    /**
     * Đường cũ parent.children.show?tab=… (vẫn còn được link từ trang chủ + thông báo cũ) — giờ
     * mỗi tab là một mục riêng của khu phụ huynh nên chuyển hướng sang đúng mục, kèm ?child=.
     * Quyền xem không đổi: các mục đích chỉ trả dữ liệu của con ĐÃ XÁC MINH (10.3), còn id lạ
     * sẽ tự rơi về con mặc định thay vì lộ dữ liệu.
     */
    public function show(Request $request, int $child): RedirectResponse
    {
        $route = match ($request->query('tab', 'overview')) {
            'schedule' => 'parent.schedule.index',
            'results' => 'parent.results.index',
            'review' => 'parent.reviews.index',
            default => 'parent.children.index',
        };

        return redirect()->route($route, ['child' => $child]);
    }
}

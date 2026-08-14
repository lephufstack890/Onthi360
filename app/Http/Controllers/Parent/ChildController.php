<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function __construct(private ChildService $childService) {}

    /** parent.children.index — chỉ hiển thị học sinh đã liên kết, không cho tìm kiếm học sinh khác (10.3, 3.3). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('parent.children.index', $this->childService->listForParent($user));
    }

    /**
     * parent.children.linkRequest — gửi yêu cầu liên kết con bằng EMAIL thật của học sinh
     * (trước đây form chỉ trang trí, không submit được).
     */
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

    /** parent.children.show (PAR-02) — lịch/điểm danh/kết quả/tiến độ/review (10.3, 9.2). */
    public function show(Request $request, int $child): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'overview');

        return view('parent.children.show', $this->childService->showForParent($user, $child, $tab));
    }
}

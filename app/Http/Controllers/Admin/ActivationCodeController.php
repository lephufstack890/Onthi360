<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Services\Admin\ActivationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ActivationCodeController extends Controller
{
    public function __construct(private ActivationCodeService $activationCodeService) {}

    public function index(Request $request): View
    {
        return view('admin.activation-codes.index', $this->activationCodeService->indexData());
    }

    /** admin.activation-codes.create — biểu mẫu cấp tay 1 mã cho đúng 1 tài khoản (SỬA 18/9). */
    public function create(Request $request): View
    {
        return view('admin.activation-codes.create', $this->activationCodeService->createFormData());
    }

    /**
     * admin.activation-codes.store — SỬA 18/9. Cấp mã xong quay về danh sách và làm NỔI BẬT
     * đúng mã vừa tạo (flash 'newCode') để admin sao chép đưa cho người dùng ngay.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'scope' => ['required', 'string', 'in:personal_learning,teacher_teaching'],
            // Bỏ trống = lấy theo thời hạn của tài liệu (hoặc vĩnh viễn nếu tài liệu cũng trống).
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'user_id' => 'Tài khoản',
            'product_id' => 'Tài liệu',
            'scope' => 'Phạm vi quyền',
            'validity_months' => 'Thời hạn',
            'note' => 'Ghi chú',
        ]);

        $code = $this->activationCodeService->store(Auth::user(), $data);

        return redirect()->route('admin.activation-codes.index')
            ->with('status', 'code-created')
            ->with('newCode', $code->code);
    }

    public function revoke(Request $request, ActivationCode $activationCode): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->activationCodeService->revoke($activationCode, $data['reason']);

        return redirect()->route('admin.activation-codes.index')->with('status', 'code-revoked');
    }
}

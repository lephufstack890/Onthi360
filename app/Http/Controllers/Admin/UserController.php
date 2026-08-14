<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentLink;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    /** admin.users.index (ADM-02) — 10.4 + 3.1/3.2. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        return view('admin.users.index', $this->userService->indexData($tab));
    }

    /** admin.users.show — 3.1/3.2 (đa vai trò) + audit log + hồ sơ theo vai trò. */
    public function show(Request $request, int $user): View
    {
        return view('admin.users.show', $this->userService->showData($user));
    }

    /** admin.users.create — form thêm người dùng mới, chọn vai trò ngay lúc tạo. */
    public function create(): View
    {
        return view('admin.users.create', $this->userService->createFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
            'password' => ['required', 'confirmed', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:'.implode(',', [Role::STUDENT, Role::TEACHER, Role::PARENT, Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN])],
        ]);

        $user = $this->userService->store(Auth::user(), $data);

        return redirect()->route('admin.users.show', $user->id)->with('status', 'user-created');
    }

    /** admin.users.edit — form sửa thông tin cơ bản. */
    public function edit(int $user): View
    {
        return view('admin.users.edit', $this->userService->editFormData($user));
    }

    /**
     * admin.users.update — chuyển status sang "suspended" PHẢI có lý do (10.4), các thay
     * đổi khác (tên/email/SĐT, hoặc mở khóa lại "active") thì không bắt buộc.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'reason' => [$request->input('status') === 'suspended' ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        $this->userService->update(Auth::user(), $user, $data, $data['reason'] ?? null);

        return redirect()->route('admin.users.show', $user->id)->with('status', 'user-updated');
    }

    /** admin.users.roles.update — gán/gỡ vai trò (4.3), ghi audit log (16 mục 4). */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.show', $user->id)->withErrors(['roles' => 'Không thể tự sửa vai trò của chính mình ở đây.']);
        }

        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:'.implode(',', [Role::STUDENT, Role::TEACHER, Role::PARENT, Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN])],
        ]);

        $this->userService->updateRoles(Auth::user(), $user, $data['roles'] ?? []);

        return redirect()->route('admin.users.show', $user->id)->with('status', 'roles-updated');
    }

    /**
     * admin.users.password.update — Admin đổi mật khẩu trực tiếp cho người dùng (note họp
     * 13/8, mục 2: "Cần có đổi mật khẩu... cho người dùng") — dùng khi người dùng không tự
     * đổi được (quên mật khẩu, mất quyền truy cập email, …).
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $this->userService->resetPassword(Auth::user(), $user, $data['password']);

        return redirect()->route('admin.users.show', $user->id)->with('status', 'password-updated');
    }

    /** admin.parent-links.approve — 10.3: "xác minh phụ huynh chặt chẽ". Không yêu cầu lý do. */
    public function approveParentLink(Request $request, ParentLink $parentLink): RedirectResponse
    {
        $this->userService->approveParentLink($parentLink, Auth::user());

        return redirect()->route('admin.users.show', $parentLink->student_user_id)->with('status', 'parent-link-approved');
    }

    /** admin.parent-links.reject — thu hồi/từ chối liên kết, PHẢI ghi lý do (16 mục 4). */
    public function rejectParentLink(Request $request, ParentLink $parentLink): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->userService->rejectParentLink($parentLink, Auth::user(), $data['reason']);

        return redirect()->route('admin.users.show', $parentLink->student_user_id)->with('status', 'parent-link-rejected');
    }
}

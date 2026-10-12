<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentLink;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\UserPermissionService;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $userService, private UserPermissionService $permissionService) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        return view('admin.users.index', $this->userService->indexData($tab));
    }

    public function show(Request $request, int $user): View
    {
        return view('admin.users.show', $this->userService->showData($user));
    }

    /** Popup "Chi tiết" ở danh sách: trả HTML (3 tab Thông tin / Phân quyền / Lịch sử) để nạp bằng fetch. */
    public function panel(Request $request, int $user): View
    {
        return view('admin.users._panel', $this->userService->panelData($user, Auth::user()));
    }

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

    public function edit(int $user): View
    {
        return view('admin.users.edit', $this->userService->editFormData($user));
    }

    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'reason' => [$request->input('status') === 'suspended' && $user->status !== 'suspended' ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        if ($user->id === Auth::id() && $data['status'] === 'suspended') {
            throw ValidationException::withMessages(['status' => 'Không thể tự khóa tài khoản của chính mình.']);
        }

        $this->userService->update(Auth::user(), $user, $data, $data['reason'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã lưu thông tin người dùng.']);
        }

        return redirect()->route('admin.users.show', $user->id)->with('status', 'user-updated');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse|JsonResponse
    {
        if ($user->id === Auth::id()) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['roles' => 'Không thể tự sửa vai trò của chính mình ở đây.']);
            }

            return redirect()->route('admin.users.show', $user->id)->withErrors(['roles' => 'Không thể tự sửa vai trò của chính mình ở đây.']);
        }

        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:'.implode(',', [Role::STUDENT, Role::TEACHER, Role::PARENT, Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN])],
        ]);

        $this->userService->updateRoles(Auth::user(), $user, $data['roles'] ?? []);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã cập nhật vai trò, đã ghi lịch sử.']);
        }

        return redirect()->route('admin.users.show', $user->id)->with('status', 'roles-updated');
    }

    /** Tab "Phân quyền" của popup: lưu bảng chức năng + phạm vi của một người dùng. */
    public function updatePermissions(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'perms' => ['nullable', 'array'],
            'perms.*.on' => ['nullable', 'boolean'],
            'perms.*.scope' => ['nullable', 'string', 'in:own,class,all'],
        ]);

        $changed = $this->permissionService->save(Auth::user(), $user, $data['perms'] ?? []);

        return response()->json([
            'message' => $changed > 0 ? "Đã lưu phân quyền ($changed thay đổi)." : 'Không có thay đổi nào để lưu.',
            'changed' => $changed,
        ]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $this->userService->resetPassword(Auth::user(), $user, $data['password']);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã đổi mật khẩu cho người dùng này.']);
        }

        return redirect()->route('admin.users.show', $user->id)->with('status', 'password-updated');
    }

    /** Xóa mềm người dùng (có lý do, có chặn tự xóa / quản trị viên cuối cùng). */
    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->userService->delete(Auth::user(), $user, $data['reason']);
        session()->flash('status', 'user-deleted');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã xóa người dùng. Có thể khôi phục ở tab "Đã xóa".']);
        }

        return redirect()->route('admin.users.index')->with('status', 'user-deleted');
    }

    public function restore(Request $request, int $user): RedirectResponse|JsonResponse
    {
        $target = User::onlyTrashed()->findOrFail($user);
        $this->userService->restore(Auth::user(), $target);
        session()->flash('status', 'user-restored');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã khôi phục người dùng.']);
        }

        return redirect()->route('admin.users.index')->with('status', 'user-restored');
    }

    public function approveParentLink(Request $request, ParentLink $parentLink): RedirectResponse
    {
        $this->userService->approveParentLink($parentLink, Auth::user());

        return redirect()->route('admin.users.show', $parentLink->student_user_id)->with('status', 'parent-link-approved');
    }

    public function rejectParentLink(Request $request, ParentLink $parentLink): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->userService->rejectParentLink($parentLink, Auth::user(), $data['reason']);

        return redirect()->route('admin.users.show', $parentLink->student_user_id)->with('status', 'parent-link-rejected');
    }
}

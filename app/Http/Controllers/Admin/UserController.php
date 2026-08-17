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

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        return view('admin.users.index', $this->userService->indexData($tab));
    }

    public function show(Request $request, int $user): View
    {
        return view('admin.users.show', $this->userService->showData($user));
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

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $this->userService->resetPassword(Auth::user(), $user, $data['password']);

        return redirect()->route('admin.users.show', $user->id)->with('status', 'password-updated');
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

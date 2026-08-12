<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\UserService;
use Illuminate\Http\Request;
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

    /** admin.users.show — 3.1/3.2 (đa vai trò) + audit log. */
    public function show(Request $request, int $user): View
    {
        return view('admin.users.show', $this->userService->showData($user));
    }
}

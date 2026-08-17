<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    /**
     * info.contact.store (PUB-11, 4.1 mục "Liên hệ") — form công khai, KHÔNG yêu cầu đăng
     * nhập (khách chưa có tài khoản cũng gửi được).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $this->contactService->store($data);

        // redirect(route(...).'#...') thay vì redirect()->route(...) vì RedirectResponse không
        // có sẵn hàm gắn fragment — cần quay lại đúng mục "Liên hệ" trên trang 1-trang-nhiều-mục.
        return redirect(route('info.index').'#lien-he')->with('status', 'contact-sent');
    }
}

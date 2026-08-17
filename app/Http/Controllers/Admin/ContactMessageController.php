<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Admin\ContactMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactMessageService $contactMessageService) {}

    /** admin.contact-messages.index — tin nhắn gửi từ form "Liên hệ" (info.index, PUB-11). */
    public function index(Request $request): View
    {
        return view('admin.contact-messages.index', $this->contactMessageService->indexData());
    }

    /** admin.contact-messages.resolve — đánh dấu 1 tin nhắn đã xử lý. */
    public function resolve(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $this->contactMessageService->resolve(Auth::user(), $contactMessage);

        return redirect()->route('admin.contact-messages.index')->with('status', 'resolved');
    }
}

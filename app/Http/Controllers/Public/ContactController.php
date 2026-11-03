<?php

namespace App\Http\Controllers\Public;

use App\Enums\SupportTopic;
use App\Http\Controllers\Controller;
use App\Services\Public\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    public function store(Request $request): RedirectResponse
    {
        // SỬA 3/11 (khách: "chọn xong bấm Yêu cầu hỗ trợ thì nhảy sang Zalo tương ứng") — chọn người hỗ trợ (to), email
        // không bắt buộc nữa (liên hệ chính qua Zalo). Phiếu vẫn được lưu để admin theo dõi ở mục Liên hệ.
        $team = \App\Services\Public\InfoService::supportTeam();
        $data = $request->validate([
            'to' => ['required', Rule::in(array_keys($team))],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            // Nhiều phụ huynh chỉ tiện nghe điện thoại nên cho nhập, nhưng không bắt buộc.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\.\s-]{8,30}$/'],
            'topic' => ['nullable', Rule::in(array_keys(SupportTopic::options()))],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'phone.regex' => 'Số điện thoại chỉ gồm chữ số và các ký tự + ( ) - . ',
            'to.required' => 'Chọn người bạn cần hỗ trợ.',
            'to.in' => 'Chọn người bạn cần hỗ trợ.',
        ]);
        $person = $team[$data['to']];
        $data['email'] = $data['email'] ?? '';
        $data['topic'] = $data['topic'] ?? $person['topic'];
        $data['message'] = '[Gửi tới: '.$person['role'].' – '.$person['name'].' · Zalo '.$person['phone']."]\n".$data['message'];
        $zaloUrl = 'https://zalo.me/'.$person['phone'];

        /*
         * Bẫy máy gửi rác: ô "website" được ẩn khỏi mắt người dùng bằng CSS, người thật không
         * bao giờ điền. Máy gửi rác điền hết mọi ô nên sẽ lộ ra ở đây.
         *
         * Gặp trường hợp đó thì KHÔNG lưu, nhưng vẫn trả về đúng màn "đã gửi" như bình thường
         * — báo thẳng "bạn là máy" chỉ giúp bên viết máy gửi rác biết đường lách. Cách này
         * cũng tránh phải dùng CAPTCHA, thứ vừa phiền vừa khó với học sinh nhỏ tuổi.
         */
        if (filled($request->input('website'))) {
            return $this->sentResponse(null);
        }

        $message = $this->contactService->store(
            $data,
            Auth::user(),
            $request->ip(),
        );

        // Lưu phiếu xong thì mở thẳng Zalo của người được chọn (nội dung đã được trang chép sẵn để dán vào Zalo).
        return redirect()->away($zaloUrl);
    }

    private function sentResponse(?string $ticketCode): RedirectResponse
    {
        return redirect(route('info.index').'#lien-he')
            ->with('status', 'contact-sent')
            ->with('contact-ticket', $ticketCode);
    }
}

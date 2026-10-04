<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use App\Services\Admin\FeaturedTeacherService;
use App\Support\UploadLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FeaturedTeacherController extends Controller
{
    public function __construct(private FeaturedTeacherService $featuredTeacherService) {}

    public function index(Request $request): View
    {
        return view('admin.featured-teachers.index', $this->featuredTeacherService->indexData());
    }

    /**
     * TẠO TÀI KHOẢN GIÁO VIÊN MỚI rồi vinh danh luôn — giống màn "Thêm người dùng" nhưng kèm
     * các trường của trang vinh danh. Vai trò cố định là Giáo viên.
     */
    public function store(Request $request)
    {
        $account = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            /*
             * users.phone là cột UNIQUE (xem migration add_profile_fields_to_users_table). Không
             * kiểm ở đây thì hai người trùng số sẽ ném lỗi CSDL trần ra màn hình thay vì một câu
             * báo lỗi đọc được. (Màn Thêm người dùng hiện cũng đang thiếu phép kiểm này.)
             */
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', 'min:8'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
        ], [
            'display_name.required' => 'Phải nhập họ tên.',
            'email.unique' => 'Email này đã có tài khoản.',
            'phone.unique' => 'Số điện thoại này đã có tài khoản.',
            'password.confirmed' => 'Hai ô mật khẩu chưa khớp nhau.',
            'password.min' => 'Mật khẩu phải từ 8 ký tự.',
        ]);

        $this->featuredTeacherService->createWithAccount(
            Auth::user(),
            $account + $this->validatePayload($request, nameRequired: true),
            $request->file('avatar'),
        );

        return back()->with('status', 'created');
    }

    /** THÊM vào danh sách vinh danh. */
    public function feature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->feature($featuredTeacher, $this->validatePayload($request), $request->file('avatar'));

        return back()->with('status', 'featured');
    }

    /** SỬA người đang vinh danh. */
    public function update(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->update($featuredTeacher, $this->validatePayload($request), $request->file('avatar'));

        return back()->with('status', 'updated');
    }

    /**
     * XOÁ KHỎI DANH SÁCH VINH DANH — không đụng tới hồ sơ hay tài khoản giáo viên,
     * xem FeaturedTeacherService::unfeature().
     */
    public function unfeature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->unfeature($featuredTeacher);

        return back()->with('status', 'unfeatured');
    }

    /**
     * XOÁ HẲN một hồ sơ trưng bày (không gắn tài khoản).
     *
     * KIỂM TRA LẠI Ở ĐÂY chứ không tin giao diện: nút này chỉ hiện trên thẻ không có tài khoản,
     * nhưng người ta gửi thẳng request thì vẫn tới được. Hồ sơ của giáo viên thật bị từ chối.
     */
    public function destroy(Request $request, TeacherProfile $featuredTeacher)
    {
        if (! $this->featuredTeacherService->deleteStandalone($featuredTeacher)) {
            return back()->withErrors([
                'delete' => 'Hồ sơ này gắn với một tài khoản giáo viên nên không xoá được từ màn vinh danh. Dùng "Rút khỏi danh sách" nếu chỉ muốn ẩn khỏi trang công khai.',
            ]);
        }

        return back()->with('status', 'deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $nameRequired = false): array
    {
        $data = $request->validate([
            'display_name' => [$nameRequired ? 'required' : 'nullable', 'string', 'max:120'],
            'workplace' => ['nullable', 'string', 'max:160'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'achievement_note' => ['nullable', 'string', 'max:2000'],
            // Chặn ngay ở đây chứ không chỉ ở ô nhập: ô number của trình duyệt chỉ gợi ý, người
            // ta gửi thẳng request vẫn lọt. 5 sao là trần, âm thì vô nghĩa.
            'display_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'is_expert' => ['nullable', 'boolean'],
            /*
             * Ảnh đại diện. Trần 4MB cho ảnh chân dung là thừa sức (ImageOptimizer còn nén về
             * 512px nữa), nhưng vẫn phải so với giới hạn THẬT của máy chủ: php.ini thường chỉ
             * cho 2M, hứa 4MB mà máy chủ cắt ở 2M thì người dùng chỉ nhận đúng câu "tải lên
             * thất bại" chẳng hiểu vì sao — đúng chuyện đã xảy ra với PDF xem trước hôm 2/10.
             */
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.UploadLimit::maxKilobytes(4096)],
            'remove_avatar' => ['nullable', 'boolean'],
            /*
             * SỬA 4/10 — 2 ô này giờ có cả ở form SỬA, ghi xuống bảng users (xem
             * FeaturedTeacherService::syncAccountFields()).
             *
             * Form nào KHÔNG có 2 ô này thì validate() cũng không trả chúng về trong mảng kết
             * quả — Laravel chỉ trả những khoá thực sự có trong request. Nhờ vậy service phân
             * biệt được "không gửi" với "gửi lên rỗng", và không xoá trắng tỉnh/thành của người
             * ta chỉ vì form đó không có ô để nhập.
             */
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
        ], [
            'display_rating.max' => 'Số sao xếp hạng không được quá 5.',
            'display_rating.min' => 'Số sao xếp hạng không được là số âm.',
            'display_name.required' => 'Phải nhập họ tên cho giáo viên / chuyên gia thêm mới.',
            'avatar.image' => 'Ảnh đại diện phải là tệp ảnh (JPG, PNG hoặc WebP).',
            'avatar.max' => 'Ảnh đại diện vượt quá '.UploadLimit::label(4096).' mà máy chủ nhận được.',
        ]);

        $data['remove_avatar'] = $request->boolean('remove_avatar');

        // Ô tick không được tick thì TRÌNH DUYỆT KHÔNG GỬI trường đó lên. Phải đọc bằng
        // $request->boolean() chứ không dựa vào validate() — nếu không, bỏ tick rồi lưu sẽ
        // không gỡ được cờ chuyên gia.
        $data['is_expert'] = $request->boolean('is_expert');

        return $data;
    }
}

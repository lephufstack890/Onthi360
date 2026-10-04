@extends('layouts.admin')

@section('title', 'Giáo viên & chuyên gia')
@section('page-title', 'Giáo viên & chuyên gia')

@section('content')
    {{--
        SỬA 4/10 (khách: "cho tách riêng đi đừng gộp với trang cuộc thi nha CRUD cho đầy đủ các
        thông tin đó") — màn đứng riêng (đã có mục menu riêng, xem partials/sidebar-admin), bỏ
        thanh tab dùng chung với Cuộc thi, và mỗi hồ sơ sửa được đủ 6 trường khách liệt kê:
        Họ tên · Đơn vị công tác · Vai trò · Chuyên gia · Thành tích tiêu biểu · Số sao xếp hạng.

        VÌ SAO BÀY THÀNH THẺ CHỨ KHÔNG PHẢI BẢNG: 6 trường kèm một danh sách gạch đầu dòng nhét
        vào bảng thì cột nào cũng chật, mà form sửa lại phải chiếm trọn chiều ngang. Nhét form
        vào một <tr> riêng bên dưới cũng không chạy — <tr> đó nằm NGOÀI phạm vi x-data của dòng
        trên nên Alpine không thấy biến. Thẻ thì form nằm gọn ngay trong cùng một khối.

        NÓI RÕ 3 CHỖ DỄ HIỂU NHẦM:
        · "Họ tên" là TÊN HIỂN THỊ trên trang vinh danh; để trống thì lấy tên tài khoản. Màn này
          không sửa tên tài khoản của người ta — đó là tên họ dùng để đăng nhập.
        · "Xoá" là RÚT TÊN KHỎI TRANG VINH DANH, không xoá hồ sơ cũng không xoá tài khoản.
        · "Số sao" là số BAN QUẢN TRỊ CÔNG BỐ, khác điểm trung bình tính từ đánh giá đã kiểm
          duyệt; để trống thì trang công khai tự quay về điểm trung bình thật.
    --}}
    @php
        $teachers = $teachers ?? [];
        $featuredList = array_values(array_filter($teachers, fn ($t) => $t['featured']));
        $restList = array_values(array_filter($teachers, fn ($t) => ! $t['featured']));
        $expertCount = count(array_filter($featuredList, fn ($t) => $t['expert']));
    @endphp

    <x-ws.page-header title="Giáo viên & chuyên gia" icon="badge-check"
                      subtitle="Hồ sơ bày ra trang công khai /giao-vien-tieu-bieu. Chỉ hiển thị dữ liệu thật/có phép (12.2)." />

    @if (session('status'))
        @php
            $statusMessages = [
                'featured' => 'Đã thêm vào danh sách vinh danh.',
                'updated' => 'Đã lưu thay đổi.',
                'unfeatured' => 'Đã rút tên khỏi trang vinh danh. Hồ sơ và tài khoản giáo viên vẫn còn nguyên.',
                'created' => 'Đã tạo tài khoản giáo viên và đưa lên trang vinh danh.',
                'deleted' => 'Đã xoá hồ sơ.',
            ];
        @endphp
        @include('partials.toast-flash', ['type' => 'success', 'message' => $statusMessages[session('status')] ?? 'Đã lưu.'])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => $errors->first()])
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 font-bold text-[#0B3C78]">
            <x-lucide name="users" class="h-3.5 w-3.5" />{{ count($featuredList) }} đang hiển thị công khai
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-bold text-amber-800">
            <x-lucide name="badge-check" class="h-3.5 w-3.5" />{{ $expertCount }} chuyên gia
        </span>
        <span class="text-slate-400">Chuyên gia luôn đứng trước ở trang công khai.</span>
    </div>

    {{-- ══════ THÊM MỚI ══════
         SỬA 4/10 (khách: "thêm cả thông tin email sđt mật khẩu các thứ nữa nha giống thêm người
         dùng luôn mà nó khác là có các thông tin kia nha. Vai trò thêm ở đây mặc định là giáo
         viên" + "tỉnh thành, khu vực nữa nhé") — TẠO TÀI KHOẢN GIÁO VIÊN THẬT ngay tại đây rồi
         vinh danh luôn, thay vì chỉ tạo một hồ sơ trưng bày như bản sáng nay.

         Khác khối "Giáo viên đã duyệt, chưa vinh danh" ở dưới: khối đó gắn vào tài khoản ĐÃ CÓ,
         còn khối này tạo tài khoản mới. Người được tạo ở đây đăng nhập được bằng email/mật khẩu
         vừa đặt, và hồ sơ được duyệt luôn (có ghi ai duyệt, duyệt lúc nào) vì chính admin vừa
         tự tay khai. --}}
    @php
        // Khuôn rỗng để dùng lại partial 6 ô nhập; profile_id = 0 vì hồ sơ chưa tồn tại.
        $blankTeacher = [
            'profile_id' => 0, 'accountName' => '', 'displayName' => '', 'name' => '',
            'workplace' => '', 'roleTitle' => '', 'subject' => '', 'subjects' => [],
            'featured' => true, 'expert' => false, 'displayRating' => null,
            'avatarPath' => null, 'ownAvatar' => null, 'province' => null, 'region' => null, 'sortOrder' => 0,
            'achievement' => '', 'achievements' => [], 'hasAccount' => false,
        ];
    @endphp

    {{-- Gửi hỏng thì mở lại form sẵn, đừng bắt người ta bấm "+ Thêm mới" rồi gõ lại từ đầu. --}}
    <div x-data="{ open: {{ old('email') !== null ? 'true' : 'false' }} }" class="mb-6 rounded-2xl border border-blue-200 bg-blue-50/60 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-black text-[#0B3C78]">Thêm giáo viên / chuyên gia mới</h2>
                <p class="mt-0.5 text-xs text-slate-500">Tạo tài khoản giáo viên mới (email · mật khẩu · SĐT · tỉnh thành · khu vực) rồi vinh danh luôn trong một lần.</p>
            </div>
            <button type="button" @click="open = ! open"
                    class="shrink-0 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                    x-text="open ? 'Đóng' : '+ Thêm giáo viên / chuyên gia'">+ Thêm giáo viên / chuyên gia</button>
        </div>

        <form x-show="open" x-cloak method="POST" enctype="multipart/form-data" action="{{ route('admin.featured-teachers.store') }}"
              class="mt-4 border-t border-blue-200 pt-4">
            @csrf
            @include('partials.featured-teacher-account-fields')
            @include('partials.featured-teacher-fields', [
                't' => $blankTeacher,
                'submitLabel' => 'Tạo tài khoản & vinh danh',
                'nameRequired' => true,
            ])
        </form>
    </div>

    {{-- ══════ ĐANG VINH DANH — sửa / xoá ══════ --}}
    <h2 class="mb-2 text-sm font-black text-slate-700">Đang hiển thị trên trang công khai</h2>

    <div class="space-y-3">
        @forelse ($featuredList as $t)
            <div x-data="{ open: false }"
                 class="rounded-2xl border bg-white p-4 shadow-sm {{ $t['expert'] ? 'border-amber-300 bg-amber-50' : 'border-sky-100' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    @if ($t['avatarPath'])
                        <img src="{{ asset('storage/'.$t['avatarPath']) }}" alt="Ảnh của {{ $t['name'] }}"
                             class="h-12 w-12 shrink-0 rounded-xl border border-sky-100 object-cover">
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-black text-[#0B3C78]">{{ $t['name'] }}</h3>
                            @if ($t['expert'])
                                <x-ws.badge tone="warning"><x-lucide name="badge-check" class="h-3 w-3" />Chuyên gia</x-ws.badge>
                            @endif
                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-500" title="Thứ tự hiển thị">
                                #{{ $t['sortOrder'] }}
                            </span>
                            @if ($t['displayRating'] !== null)
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600">
                                    <x-lucide name="star" class="h-3.5 w-3.5" style="fill: currentColor" />{{ number_format($t['displayRating'], 1) }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $t['roleTitle'] ?: ($t['subject'] ? 'Giáo viên '.$t['subject'] : 'Chưa đặt vai trò') }}
                            @if ($t['workplace']) · {{ $t['workplace'] }} @endif
                        </p>
                        @if ($t['hasAccount'] && ($t['province'] || $t['region']))
                            @php
                                $regionLabels = \App\Support\VietnamProvinces::regionOptions();
                            @endphp
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                {{ collect([$t['province'], $regionLabels[$t['region']] ?? null])->filter()->implode(' · ') }}
                            </p>
                        @endif

                        @if (! $t['hasAccount'])
                            <p class="mt-0.5 text-[10px] text-slate-400">Hồ sơ trưng bày — không gắn tài khoản, nên không có lớp phụ trách hay đánh giá.</p>
                        @elseif ($t['displayName'] !== '' && $t['displayName'] !== $t['accountName'])
                            <p class="mt-0.5 text-[10px] text-slate-400">Tài khoản: {{ $t['accountName'] }}</p>
                        @endif

                        @if (count($t['achievements']) > 0)
                            <ul class="mt-2 list-disc space-y-0.5 pl-5 text-[11px] text-slate-600">
                                @foreach ($t['achievements'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-3 text-xs">
                        <button type="button" @click="open = ! open" class="font-medium text-blue-600"
                                x-text="open ? 'Thu gọn' : 'Sửa'">Sửa</button>

                        {{-- Hồ sơ GẮN TÀI KHOẢN: chỉ rút khỏi trang, không xoá — hồ sơ ấy còn kéo
                             theo lớp, đánh giá, bài giao của người đó.
                             Hồ sơ TRƯNG BÀY (không tài khoản): xoá hẳn, vì rút xuống rồi thì không
                             còn chỗ nào tìm lại được, để đó chỉ thành rác. --}}
                        @if ($t['hasAccount'])
                            <form method="POST" action="{{ route('admin.featured-teachers.unfeature', $t['profile_id']) }}"
                                  onsubmit="return confirm('Rút {{ $t['name'] }} khỏi trang vinh danh? Hồ sơ và tài khoản giáo viên vẫn giữ nguyên.');">
                                @csrf
                                <button type="submit" class="font-medium text-rose-600">Rút khỏi danh sách</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.featured-teachers.destroy', $t['profile_id']) }}"
                                  onsubmit="return confirm('Xoá hẳn hồ sơ {{ $t['name'] }}? Hồ sơ này không gắn tài khoản nào nên xoá là mất luôn, không khôi phục được.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-rose-600">Xoá hẳn</button>
                            </form>
                        @endif
                    </div>
                </div>

                <form x-show="open" x-cloak method="POST" enctype="multipart/form-data"
                      action="{{ route('admin.featured-teachers.update', $t['profile_id']) }}"
                      class="mt-4 border-t border-sky-100 pt-4">
                    @csrf
                    @method('PUT')
                    @include('partials.featured-teacher-fields', ['t' => $t, 'submitLabel' => 'Lưu thay đổi'])
                </form>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-sky-200 p-6 text-center text-sm text-slate-400">
                Chưa vinh danh ai. Chọn ở danh sách bên dưới.
            </p>
        @endforelse
    </div>

    {{-- ══════ CHƯA VINH DANH — thêm ══════ --}}
    <h2 class="mb-2 mt-8 text-sm font-black text-slate-700">Giáo viên đã duyệt, chưa vinh danh</h2>

    <div class="space-y-3">
        @forelse ($restList as $t)
            <div x-data="{ open: false }" class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-700">{{ $t['name'] }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ count($t['subjects']) > 0 ? implode(' · ', $t['subjects']) : 'Chưa khai môn dạy' }}
                        </p>
                    </div>
                    <button type="button" @click="open = ! open" class="shrink-0 text-xs font-medium text-blue-600"
                            x-text="open ? 'Thu gọn' : 'Thêm vào danh sách'">Thêm vào danh sách</button>
                </div>

                <form x-show="open" x-cloak method="POST" enctype="multipart/form-data"
                      action="{{ route('admin.featured-teachers.feature', $t['profile_id']) }}"
                      class="mt-4 border-t border-sky-100 pt-4">
                    @csrf
                    @include('partials.featured-teacher-fields', ['t' => $t, 'submitLabel' => 'Thêm vào trang vinh danh'])
                </form>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-sky-200 p-6 text-center text-sm text-slate-400">
                Không còn giáo viên đã duyệt nào ngoài danh sách.
            </p>
        @endforelse
    </div>
@endsection

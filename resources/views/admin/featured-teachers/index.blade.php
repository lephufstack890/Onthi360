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

    {{-- ══════ ĐANG VINH DANH — sửa / xoá ══════ --}}
    <h2 class="mb-2 text-sm font-black text-slate-700">Đang hiển thị trên trang công khai</h2>

    <div class="space-y-3">
        @forelse ($featuredList as $t)
            <div x-data="{ open: false }"
                 class="rounded-2xl border bg-white p-4 shadow-sm {{ $t['expert'] ? 'border-amber-300 bg-amber-50' : 'border-sky-100' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-black text-[#0B3C78]">{{ $t['name'] }}</h3>
                            @if ($t['expert'])
                                <x-ws.badge tone="warning"><x-lucide name="badge-check" class="h-3 w-3" />Chuyên gia</x-ws.badge>
                            @endif
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
                        @if ($t['displayName'] !== '' && $t['displayName'] !== $t['accountName'])
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

                        <form method="POST" action="{{ route('admin.featured-teachers.unfeature', $t['profile_id']) }}"
                              onsubmit="return confirm('Rút {{ $t['name'] }} khỏi trang vinh danh? Hồ sơ và tài khoản giáo viên vẫn giữ nguyên.');">
                            @csrf
                            <button type="submit" class="font-medium text-rose-600">Xoá khỏi danh sách</button>
                        </form>
                    </div>
                </div>

                <form x-show="open" x-cloak method="POST"
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

                <form x-show="open" x-cloak method="POST"
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

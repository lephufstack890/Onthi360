@extends('layouts.admin')

@section('title', 'Giáo viên và chuyên gia')
@section('page-title', 'Giáo viên và chuyên gia')

@section('content')
    {{--
        SỬA 4/10 (khách: "trong admin ông CRUD thêm cho tôi chỗ giáo viên chuyên gia cho tôi luôn
        nha. Admin có thể thêm sửa xoá") — màn này trước chỉ có THÊM (vinh danh) và XOÁ (bỏ vinh
        danh). Thiếu hẳn SỬA: muốn đổi câu thành tích đã công bố thì phải gỡ xuống rồi vinh danh
        lại, trong khoảng đó người ấy biến mất khỏi trang công khai.

        Nay mỗi dòng có đủ 3 việc, và thêm ô "Là chuyên gia" — cờ này quyết định huy hiệu và thứ
        tự ở trang công khai (xem Public\TeacherService::featuredData()).

        NÓI RÕ VỀ CHỮ "XOÁ": ở màn này nó là RÚT TÊN KHỎI TRANG VINH DANH, không xoá hồ sơ cũng
        không xoá tài khoản giáo viên. Nút ghi đúng như vậy để không ai bấm nhầm. Xoá thật hồ sơ
        kéo theo lớp, đánh giá, bài giao của người đó — việc ấy nằm ở màn duyệt giáo viên.
    --}}
    @php
        $tabs = $tabs ?? [];
        $teachers = $teachers ?? [];
        $featuredList = array_values(array_filter($teachers, fn ($t) => $t['featured']));
        $restList = array_values(array_filter($teachers, fn ($t) => ! $t['featured']));
        $expertCount = count(array_filter($featuredList, fn ($t) => $t['expert'] ?? false));
    @endphp

    <x-ws.page-header title="Giáo viên và chuyên gia" icon="trophy"
                      subtitle="Chỉ hiển thị dữ liệu thật/có phép; không lộ số điện thoại cá nhân (12.2)." />

    @if (session('status'))
        @php
            $statusMessages = [
                'featured' => 'Đã thêm vào danh sách vinh danh.',
                'updated' => 'Đã lưu thay đổi.',
                'unfeatured' => 'Đã rút tên khỏi trang vinh danh. Hồ sơ và tài khoản giáo viên vẫn còn nguyên.',
            ];
        @endphp
        @include('partials.toast-flash', [
            'type' => 'success',
            'message' => $statusMessages[session('status')] ?? 'Đã lưu.',
        ])
    @endif

    <x-ws.tabs :tabs="$tabs" />

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
    <x-ws.table :columns="['Giáo viên', 'Môn', 'Thành tích công bố', 'Chuyên gia', '']">
        @forelse ($featuredList as $t)
            <tr x-data="{ editing: false }">
                <td class="px-4 py-3 align-top font-medium text-slate-700">{{ $t['name'] }}</td>
                <td class="px-4 py-3 align-top text-slate-500">{{ $t['subject'] ?: '—' }}</td>
                <td class="px-4 py-3 align-top text-slate-400">
                    <span x-show="! editing" class="line-clamp-2 max-w-xs">{{ $t['achievement'] ?: '—' }}</span>

                    <form x-show="editing" x-cloak method="POST"
                          action="{{ route('admin.featured-teachers.update', $t['profile_id']) }}"
                          class="w-72 space-y-2 text-left">
                        @csrf
                        @method('PUT')
                        <textarea name="achievement" rows="3" placeholder="Mỗi dòng (hoặc dấu ;) là một thành tích"
                                  class="admin-input">{{ $t['achievement'] }}</textarea>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input type="checkbox" name="is_expert" value="1" @checked($t['expert'] ?? false)
                                   class="h-4 w-4 rounded border-sky-200 text-amber-600">
                            Là chuyên gia (gắn huy hiệu, lên đầu danh sách)
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-medium text-white">Lưu</button>
                            <button type="button" @click="editing = false" class="rounded-xl border border-sky-100 px-3 py-1.5 text-xs text-slate-500">Huỷ</button>
                        </div>
                    </form>
                </td>
                <td class="px-4 py-3 align-top">
                    @if ($t['expert'] ?? false)
                        <x-ws.badge tone="warning">Chuyên gia</x-ws.badge>
                    @else
                        <span class="text-xs text-slate-400">Giáo viên</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right align-top">
                    <div class="flex items-center justify-end gap-3" x-show="! editing">
                        <button type="button" @click="editing = true" class="font-medium text-blue-600">Sửa</button>

                        {{-- Hỏi lại trước khi rút tên: đây là thay đổi nhìn thấy ngay ngoài trang công khai. --}}
                        <form method="POST" action="{{ route('admin.featured-teachers.unfeature', $t['profile_id']) }}"
                              onsubmit="return confirm('Rút {{ addslashes($t['name']) }} khỏi trang vinh danh? Hồ sơ và tài khoản giáo viên vẫn giữ nguyên.');">
                            @csrf
                            <button type="submit" class="font-medium text-rose-600">Xoá khỏi danh sách</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa vinh danh ai. Chọn ở bảng bên dưới.</td></tr>
        @endforelse
    </x-ws.table>

    {{-- ══════ CHƯA VINH DANH — thêm ══════ --}}
    <h2 class="mb-2 mt-8 text-sm font-black text-slate-700">Giáo viên đã duyệt, chưa vinh danh</h2>
    <x-ws.table :columns="['Giáo viên', 'Môn', 'Thành tích đã lưu', '']">
        @forelse ($restList as $t)
            <tr x-data="{ adding: false }">
                <td class="px-4 py-3 align-top font-medium text-slate-700">{{ $t['name'] }}</td>
                <td class="px-4 py-3 align-top text-slate-500">{{ $t['subject'] ?: '—' }}</td>
                <td class="px-4 py-3 max-w-xs align-top text-slate-400">
                    <span class="line-clamp-2">{{ $t['achievement'] ?: '—' }}</span>
                </td>
                <td class="px-4 py-3 text-right align-top">
                    <button type="button" @click="adding = true" x-show="! adding" class="font-medium text-blue-600">Thêm vào danh sách</button>

                    <form x-show="adding" x-cloak method="POST"
                          action="{{ route('admin.featured-teachers.feature', $t['profile_id']) }}"
                          class="w-72 space-y-2 text-left">
                        @csrf
                        <textarea name="achievement" rows="3" placeholder="Mỗi dòng (hoặc dấu ;) là một thành tích"
                                  class="admin-input">{{ $t['achievement'] }}</textarea>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input type="checkbox" name="is_expert" value="1" @checked($t['expert'] ?? false)
                                   class="h-4 w-4 rounded border-sky-200 text-amber-600">
                            Là chuyên gia (gắn huy hiệu, lên đầu danh sách)
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-medium text-white">Xác nhận</button>
                            <button type="button" @click="adding = false" class="rounded-xl border border-sky-100 px-3 py-1.5 text-xs text-slate-500">Huỷ</button>
                        </div>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Không còn giáo viên đã duyệt nào ngoài danh sách.</td></tr>
        @endforelse
    </x-ws.table>
@endsection

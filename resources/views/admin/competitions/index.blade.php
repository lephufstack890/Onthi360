@extends('layouts.admin')

@section('title', 'Cuộc thi')
@section('page-title', 'Cuộc thi')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $competitions = $competitions ?? [];
        $pendingTotal = $pendingTotal ?? 0;
        $pendingListed = $pendingListed ?? $pendingTotal;
        // Chỉ những cuộc thi ĐANG có đơn chờ — dùng cho dải nhắc việc ở đầu trang.
        $needReview = $needReview ?? [];
        $filters = $filters ?? ['q' => '', 'loai' => '', 'lo_trinh' => null];
        $courseOptions = $courseOptions ?? [];
        $filtered = $filters['q'] !== '' || $filters['loai'] !== '' || $filters['lo_trinh'] !== null;
        $competitionStatusMessage = match (session('status')) {
            'competition-archived' => 'Đã lưu trữ cuộc thi.',
            default => null,
        };
    @endphp
    @if ($competitionStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $competitionStatusMessage])
    @endif
    @if (session('competition_deleted'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('competition_deleted')])
    @endif

    <x-ws.page-header title="Cuộc thi" icon="trophy" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện.">
        <x-slot:actions>
            <a href="{{ route('admin.competitions.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo cuộc thi</a>
        </x-slot:actions>
    </x-ws.page-header>

    {{-- SỬA 25/10 (khách: "bỏ đi") — bỏ thanh tab "Cuộc thi" (chỉ có 1 tab, thừa). --}}

    {{--
        SỬA 19/9 (10) (khách: "học sinh đăng ký cuộc thi đó admin không biết cuộc thi nào đang
        đăng ký để duyệt") — DẢI NHẮC VIỆC.

        Trước đây danh sách chỉ có Tên/Loại/Xem, đơn đăng ký nằm sâu trong trang chi tiết của
        từng cuộc thi, nên muốn biết có ai xin vào thi thì phải mở lần lượt từng cuộc thi ra
        dò. Giờ mở màn Cuộc thi là thấy ngay, bấm thẳng vào đúng chỗ duyệt.
    --}}
    @if ($pendingTotal > 0)
        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700">
                    <x-lucide name="user-check" class="h-4 w-4" />
                </span>
                <p class="min-w-0 text-sm font-bold text-amber-900">
                    Có {{ $pendingTotal }} đơn đăng ký đang chờ bạn duyệt
                </p>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($needReview as $c)
                    <a href="{{ route('admin.competitions.show', $c['id']) }}#don-dang-ky"
                       class="inline-flex min-h-9 items-center gap-2 rounded-xl border border-amber-200 bg-white px-3 py-1.5 text-xs font-bold text-amber-900 transition-colors hover:border-amber-300 hover:bg-amber-100">
                        <span class="min-w-0 truncate">{{ $c['name'] }}</span>
                        <span class="shrink-0 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-black text-white">{{ $c['pendingRegistrations'] }}</span>
                    </a>
                @endforeach
            </div>
            @if ($pendingTotal > $pendingListed)
                {{-- Bảng chỉ liệt kê 50 cuộc thi mới nhất — nói thẳng phần còn lại nằm đâu,
                     thay vì để hai con số lệch nhau mà không giải thích. --}}
                <p class="mt-2 text-xs font-medium text-amber-800">
                    Còn {{ $pendingTotal - $pendingListed }} đơn ở các cuộc thi cũ hơn — dùng ô tìm kiếm bên dưới để mở đúng cuộc thi.
                </p>
            @endif
        </div>
    @endif

    {{--
      TẠM ẨN 24/8: Khách hiện không cần hiện cột Bắt đầu/Kết thúc/Trạng thái ở danh sách cuộc
      thi (đang thừa). Header cột là 1 mảng PHP literal trong thuộc tính :columns nên không
      thể tự comment riêng từng cột — giữ nguyên bản ĐẦY ĐỦ ở đây, sau này cần dùng lại thì
      dán nguyên khối comment này để THAY THẾ khối <x-ws.table> đang chạy ngay dưới:

    <x-ws.table :columns="['Tên', 'Loại', 'Bắt đầu', 'Kết thúc', 'Trạng thái', '']">
        @forelse ($competitions as $c)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['startsAtLabel'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['endsAtLabel'] }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$c['tone']">{{ $c['status'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.competitions.show', $c['id']) }}" class="text-blue-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có cuộc thi nào.</td></tr>
        @endforelse
    </x-ws.table>
    --}}

    {{-- SỬA 25/10 (khách: "trang cuộc thi trong admin cũng phân trang + có bộ lọc như ngoài") — Tìm theo tên · Lộ trình · Loại sự kiện. --}}
    <form method="GET" action="{{ route('admin.competitions.index') }}"
          class="mb-4 flex flex-wrap items-end gap-2.5 rounded-3xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
        <div class="min-w-[200px] flex-1">
            <label for="q" class="mb-1 block text-[11px] font-bold text-slate-600">Tìm theo tên</label>
            <div class="relative">
                <x-lucide name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100"
                       placeholder="Nhập tên cuộc thi, khảo sát hoặc khoá học…" class="admin-input" style="padding-left: 2.25rem;">
            </div>
        </div>
        @if ($courseOptions !== [])
            <div class="w-full sm:w-60">
                <label for="lo_trinh" class="mb-1 block text-[11px] font-bold text-slate-600">Lộ trình</label>
                <x-ws.select id="lo_trinh" name="lo_trinh">
                    <option value="">Tất cả lộ trình</option>
                    @foreach ($courseOptions as $co)
                        <option value="{{ $co['id'] }}" @selected($filters['lo_trinh'] === $co['id'])>{{ $co['title'] }}</option>
                    @endforeach
                </x-ws.select>
            </div>
        @endif
        <div class="w-full sm:w-48">
            <label for="loai" class="mb-1 block text-[11px] font-bold text-slate-600">Loại sự kiện</label>
            <x-ws.select id="loai" name="loai">
                <option value="">Tất cả loại</option>
                <option value="contest" @selected($filters['loai'] === 'contest')>Cuộc thi</option>
                <option value="survey" @selected($filters['loai'] === 'survey')>Khảo sát năng lực</option>
            </x-ws.select>
        </div>
        <div class="flex items-center gap-2">
            <x-ws.btn type="submit" variant="primary" icon="filter">Lọc</x-ws.btn>
            @if ($filtered)
                <x-ws.btn :href="route('admin.competitions.index')" variant="ghost">Bỏ lọc</x-ws.btn>
            @endif
        </div>
    </form>

    {{-- SỬA 24/10 (khách: "bỏ cột Đơn chờ duyệt, thêm nút Sửa và Xoá bên ngoài") — dải nhắc đơn chờ duyệt phía trên vẫn giữ. --}}
    <x-ws.table :columns="['Ảnh', 'Tên', 'Loại', '']">
        @forelse ($competitions as $c)
            <tr>
                {{-- SỬA 24/10 — cột Ảnh bìa (ảnh tải lên / chọn từ catalog / mặc định). --}}
                <td class="px-4 py-2 w-28"><img src="{{ $c['image'] }}" alt="" loading="lazy" style="width:88px;height:50px;object-fit:cover;border-radius:10px;border:1px solid #e0ecf5;display:block"></td>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                        <a href="{{ route('admin.competitions.show', $c['id']) }}" class="text-blue-600 font-medium">Xem</a>
                        <a href="{{ route('admin.competitions.edit', $c['id']) }}" class="inline-flex items-center gap-1 text-slate-600 font-medium hover:text-blue-600"><x-lucide name="pencil" class="h-3.5 w-3.5" />Sửa</a>
                        <form method="POST" action="{{ route('admin.competitions.destroy', $c['id']) }}" class="inline"
                              onsubmit="return confirm({{ Js::from('XOÁ VĨNH VIỄN cuộc thi "'.$c['name'].'"? Sẽ xoá luôn: các kỳ thi, đơn đăng ký, bài làm & điểm của học sinh, bảng xếp hạng, ảnh bìa và các đề thi chỉ dùng cho cuộc thi này (kèm file PDF). Đề đang dùng ở chỗ khác sẽ được giữ lại. Không thể khôi phục. Tiếp tục?') }});">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 font-medium text-rose-600 hover:text-rose-700"><x-lucide name="trash-2" class="h-3.5 w-3.5" />Xoá</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">{{ $filtered ? 'Không có cuộc thi / khảo sát nào khớp bộ lọc.' : 'Chưa có cuộc thi nào.' }}</td></tr>
        @endforelse
    </x-ws.table>

    {{-- SỬA 25/10 — phân trang (thanh dùng chung partials.pager). --}}
    @include('partials.pager', ['paginator' => $paginator ?? null, 'unit' => 'cuộc thi / khảo sát'])
@endsection

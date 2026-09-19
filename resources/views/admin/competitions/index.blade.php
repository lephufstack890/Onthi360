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
        $needReview = array_values(array_filter($competitions, fn ($c) => ($c['pendingRegistrations'] ?? 0) > 0));
        $competitionStatusMessage = match (session('status')) {
            'competition-archived' => 'Đã lưu trữ cuộc thi.',
            default => null,
        };
    @endphp
    @if ($competitionStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $competitionStatusMessage])
    @endif

    <x-ws.page-header title="Cuộc thi" icon="trophy" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện.">
        <x-slot:actions>
            <a href="{{ route('admin.competitions.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo cuộc thi</a>
        </x-slot:actions>
    </x-ws.page-header>

    <x-ws.tabs :tabs="$tabs" />

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
                    Còn {{ $pendingTotal - $pendingListed }} đơn thuộc cuộc thi cũ hơn, không nằm trong 50 cuộc thi mới nhất bên dưới.
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

    <x-ws.table :columns="['Tên', 'Loại', 'Đơn chờ duyệt', '']">
        @forelse ($competitions as $c)
            @php $pending = $c['pendingRegistrations'] ?? 0; @endphp
            {{-- Tô nền vàng cả dòng khi có đơn chờ: lướt bảng là thấy ngay dòng nào cần xử lý. --}}
            <tr class="{{ $pending > 0 ? 'bg-amber-50/60' : '' }}">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3">
                    @if ($pending > 0)
                        <a href="{{ route('admin.competitions.show', $c['id']) }}#don-dang-ky"
                           class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 transition-colors hover:bg-amber-100">
                            <x-lucide name="user-check" class="h-3.5 w-3.5 shrink-0" />{{ $pending }} đơn chờ duyệt
                        </a>
                    @else
                        <span class="text-xs text-slate-400">Không có</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.competitions.show', $c['id']) }}" class="text-blue-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có cuộc thi nào.</td></tr>
        @endforelse
    </x-ws.table>
@endsection

@extends('layouts.admin')

@section('title', 'Yêu cầu hỗ trợ')
@section('page-title', 'Yêu cầu hỗ trợ')

@section('content')
{{-- ═══════ YÊU CẦU HỖ TRỢ ═══════
     Nội dung người dùng gửi từ form "Gửi yêu cầu hỗ trợ" ở trang Thông tin công khai.
     CHỈ QUẢN TRỊ VIÊN vào được — đường dẫn nằm trong nhóm role:admin,super_admin.

     SỬA 13/9 — dựng lại toàn bộ: ô thống kê, lọc theo trạng thái và loại, tìm kiếm, mở xem
     nội dung đầy đủ ngay tại dòng, nhận việc / đánh dấu đã xử lý / đánh dấu rác, ghi chú nội
     bộ và nút trả lời qua email kèm sẵn mã phiếu. --}}
@php
    $statusTabs = $statusTabs ?? [];
    $topicOptions = $topicOptions ?? [];
    $filters = $filters ?? ['status' => null, 'topic' => null, 'q' => null];
    $stats = $stats ?? ['new' => 0, 'inProgress' => 0, 'resolved' => 0, 'today' => 0];
    $messages = $messages ?? [];
    $total = $total ?? 0;
    $schemaReady = $schemaReady ?? true;
@endphp

@if (session('status') === 'contact-status-changed')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã chuyển phiếu sang "'.session('contact-status-label', 'trạng thái mới').'".'])
@elseif (session('status') === 'contact-note-saved')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu ghi chú nội bộ.'])
@elseif (session('status') === 'contact-note-unavailable')
    @include('partials.toast-flash', ['type' => 'warning', 'message' => 'Chưa ghi chú được — cần chạy lệnh cập nhật cơ sở dữ liệu trước.'])
@endif

<x-ws.page-header title="Yêu cầu hỗ trợ" icon="headphones"
                  subtitle="Phiếu gửi từ form ở trang Thông tin công khai. Chỉ quản trị viên đọc được nội dung tại đây.">
    <x-slot:actions>
        <x-ws.btn :href="route('info.index').'#lien-he'" variant="onhero-ghost" icon="eye">Xem form ngoài trang công khai</x-ws.btn>
    </x-slot:actions>
</x-ws.page-header>

@unless ($schemaReady)
    {{-- Mã nguồn mới đã lên nhưng CSDL chưa cập nhật — nói rõ thiếu gì và cần gõ lệnh nào. --}}
    <div class="flex items-start gap-3 rounded-3xl border border-amber-200 bg-amber-50 p-4">
        <x-ws.icon-tile icon="alert-triangle" tone="amber" />
        <p class="flex-1 text-[13px] leading-relaxed text-amber-800">
            <strong>Cần chạy lệnh cập nhật cơ sở dữ liệu.</strong>
            Màn này vẫn xem và xử lý phiếu bình thường, nhưng <strong>mã phiếu, số điện thoại, loại yêu cầu
            và ghi chú nội bộ</strong> chưa lưu được vì bảng chưa có các cột đó. Mở thư mục dự án rồi chạy:
            <code class="mt-1 inline-block rounded-lg border border-amber-200 bg-white px-2 py-0.5 font-mono text-[12px] text-amber-900">php artisan migrate</code>
        </p>
    </div>
@endunless

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-ws.stat label="Chờ tiếp nhận" :value="$stats['new']" tone="amber" icon="mail"
               :hint="$stats['new'] > 0 ? 'Chưa ai nhận xử lý' : 'Không còn phiếu nào chờ'" />
    <x-ws.stat label="Đang xử lý" :value="$stats['inProgress']" tone="blue" icon="clock-3" hint="Đã có người nhận" />
    <x-ws.stat label="Đã xử lý" :value="$stats['resolved']" tone="emerald" icon="check-circle-2" hint="Tổng từ trước tới nay" />
    <x-ws.stat label="Gửi hôm nay" :value="$stats['today']" tone="violet" icon="inbox" hint="Tính theo ngày trên máy chủ" />
</div>

<x-ws.tabs :tabs="$statusTabs" />

{{-- Thanh lọc — giữ nguyên trạng thái đang chọn khi tìm kiếm để không mất bộ lọc. --}}
<form method="GET" action="{{ route('admin.contact-messages.index') }}"
      class="flex flex-wrap items-end gap-2.5 rounded-3xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
    @if ($filters['status'])
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
    @endif

    <div class="min-w-[200px] flex-1">
        <label for="q" class="mb-1 block text-[11px] font-bold text-slate-600">Tìm kiếm</label>
        <div class="relative">
            <x-lucide name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="100"
                   placeholder="Mã phiếu, tên, email, số điện thoại hoặc một cụm trong nội dung..."
                   {{-- .admin-input khai báo ngoài layer nên class pl-9 của Tailwind không đè được;
                        chừa chỗ cho icon kính lúp bằng style nội tuyến cho chắc. --}}
                   class="admin-input" style="padding-left: 2.25rem;">
        </div>
    </div>

    <div class="w-full sm:w-56 @unless ($schemaReady) hidden @endunless">
        <label for="topic" class="mb-1 block text-[11px] font-bold text-slate-600">Loại yêu cầu</label>
        <x-ws.select id="topic" name="topic">
            <option value="">Tất cả loại</option>
            @foreach ($topicOptions as $value => $label)
                <option value="{{ $value }}" @selected($filters['topic'] === $value)>{{ $label }}</option>
            @endforeach
        </x-ws.select>
    </div>

    <div class="flex items-center gap-2">
        <x-ws.btn type="submit" variant="primary" icon="filter">Lọc</x-ws.btn>
        @if ($filters['q'] || $filters['topic'])
            <x-ws.btn :href="route('admin.contact-messages.index', array_filter(['status' => $filters['status']]))" variant="ghost">Bỏ lọc</x-ws.btn>
        @endif
    </div>
</form>

@if (count($messages) === 0)
    <x-ws.empty-state icon="headphones"
                      :title="$filters['q'] || $filters['topic'] || $filters['status'] ? 'Không có phiếu nào khớp bộ lọc' : 'Chưa có yêu cầu hỗ trợ nào'"
                      description="Phiếu sẽ tự hiện ở đây ngay khi có người gửi từ trang Thông tin công khai." />
@else
    <x-ws.table :columns="['Mã phiếu', 'Người gửi', 'Nội dung', 'Thời gian', 'Trạng thái', '']" min-width="min-w-[1080px]">
        @foreach ($messages as $m)
            <tr x-data="{ open: false }" class="align-top">
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-black tracking-wide text-slate-600">
                        <x-lucide name="ticket" class="h-3 w-3" />{{ $m['ticket'] ?? '—' }}
                    </span>
                    <div class="mt-1.5">
                        <x-ws.badge :tone="$m['topicTone']">{{ $m['topic'] }}</x-ws.badge>
                    </div>
                </td>

                <td class="px-4 py-3">
                    <p class="text-[13px] font-bold text-slate-700">{{ $m['name'] }}</p>
                    <a href="mailto:{{ $m['email'] }}" class="block truncate text-[11px] text-blue-600 hover:underline">{{ $m['email'] }}</a>
                    @if ($m['phone'])
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $m['phone']) }}"
                           class="mt-0.5 inline-flex items-center gap-1 text-[11px] text-slate-500 hover:text-blue-700">
                            <x-lucide name="phone" class="h-3 w-3" />{{ $m['phone'] }}
                        </a>
                    @endif
                    @if ($m['accountUrl'])
                        <a href="{{ $m['accountUrl'] }}" class="mt-1 inline-flex items-center gap-1 text-[11px] font-bold text-slate-500 hover:text-blue-700">
                            <x-lucide name="user-round" class="h-3 w-3" />Tài khoản: {{ $m['accountName'] }}
                        </a>
                    @else
                        <p class="mt-1 text-[11px] text-slate-400">Khách chưa đăng nhập</p>
                    @endif
                </td>

                <td class="max-w-md px-4 py-3">
                    <p class="whitespace-pre-line text-[13px] leading-relaxed text-slate-600" :class="open ? '' : 'line-clamp-2'">{{ $m['message'] }}</p>
                    <button type="button" @click="open = !open" class="mt-1 text-[11px] font-bold text-blue-600 hover:underline"
                            x-text="open ? 'Thu gọn' : @js($schemaReady ? 'Xem đầy đủ & ghi chú' : 'Xem đầy đủ')"></button>

                    @if ($schemaReady)
                    <div x-show="open" x-cloak class="mt-2.5 rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3">
                        <form method="POST" action="{{ route('admin.contact-messages.note', $m['id']) }}" class="space-y-2">
                            @csrf
                            <label for="note-{{ $m['id'] }}" class="flex items-center gap-1.5 text-[11px] font-bold text-slate-600">
                                <x-lucide name="sticky-note" class="h-3.5 w-3.5 text-slate-400" />
                                Ghi chú nội bộ — chỉ quản trị viên đọc, không gửi cho người gửi phiếu
                            </label>
                            <textarea id="note-{{ $m['id'] }}" name="admin_note" rows="2" maxlength="2000" class="admin-input"
                                      placeholder="Đã gọi lúc 9h, hẹn gửi lại ảnh màn hình...">{{ $m['note'] }}</textarea>
                            <x-ws.btn type="submit" size="sm" variant="ghost" icon="save">Lưu ghi chú</x-ws.btn>
                        </form>
                    </div>
                    @endif

                    @if ($m['note'])
                        <p x-show="!open" class="mt-1.5 inline-flex items-start gap-1 text-[11px] italic text-slate-400">
                            <x-lucide name="sticky-note" class="mt-0.5 h-3 w-3 shrink-0" />{{ \Illuminate\Support\Str::limit($m['note'], 60) }}
                        </p>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <p class="text-[12px] font-medium text-slate-600">{{ $m['createdAt'] }}</p>
                    <p class="text-[11px] text-slate-400">{{ $m['createdAgo'] }}</p>
                </td>

                <td class="px-4 py-3">
                    <x-ws.badge :tone="$m['statusTone']">
                        <x-lucide :name="$m['statusIcon']" class="h-3 w-3" />{{ $m['statusLabel'] }}
                    </x-ws.badge>
                    @if ($m['handledBy'])
                        <p class="mt-1 text-[11px] text-slate-400">{{ $m['handledBy'] }}<br>{{ $m['handledAt'] }}</p>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <div class="flex flex-col items-stretch gap-1.5">
                        <x-ws.btn :href="$m['mailto']" size="sm" variant="soft" icon="send">Trả lời qua email</x-ws.btn>

                        @if ($m['status'] === 'new')
                            <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="in_progress">
                                <x-ws.btn type="submit" size="sm" variant="ghost" icon="clock-3" class="w-full">Nhận xử lý</x-ws.btn>
                            </form>
                        @endif

                        @unless ($m['resolved'])
                            <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="resolved">
                                <x-ws.btn type="submit" size="sm" variant="success" icon="check-circle-2" class="w-full">Đã xử lý</x-ws.btn>
                            </form>
                        @endunless

                        @if ($m['open'])
                            <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="spam">
                                <x-ws.btn type="submit" size="sm" variant="danger" icon="ban" class="w-full">Đánh dấu rác</x-ws.btn>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="new">
                                <x-ws.btn type="submit" size="sm" variant="ghost" icon="rotate-ccw" class="w-full">Mở lại</x-ws.btn>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ws.table>

    <x-ws.pagination-note :shown="count($messages)" :total="$total" />
@endif
@endsection

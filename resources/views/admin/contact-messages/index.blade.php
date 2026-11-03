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
    {{-- SỬA 3/11 (khách: "bỏ cột Mã phiếu cho gọn, ghép Trạng thái vào chỗ khác, Xem đầy đủ thì mở modal") —
         mã phiếu + trạng thái + loại gom thành 1 dòng nhãn nhỏ trên Nội dung; "Xem chi tiết" mở hộp thoại (nội dung đủ, ghi chú
         nội bộ, các nút xử lý). Form / route / dữ liệu giữ nguyên như cũ. --}}
    <style>
        .cmx-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-bottom: 6px; }
        .cmx-ticket { display: inline-flex; align-items: center; gap: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10.5px; font-weight: 700; color: #64748b; }
        .cmx-ticket svg { width: 11px; height: 11px; }
        .cmx-more { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; padding: 0; border: 0; background: none; font-size: 11px; font-weight: 700; color: #2563eb; cursor: pointer; }
        .cmx-more:hover { text-decoration: underline; }
        .cmx-more svg { width: 12px; height: 12px; }
        .cmx-handled { margin-top: 6px; font-size: 11px; line-height: 1.45; color: #94a3b8; }
        .cmx-back { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 35, 60, .45); backdrop-filter: blur(2px); }
        .cmx-dlg { width: min(640px, 100%); max-height: calc(100dvh - 32px); display: flex; flex-direction: column; overflow: hidden; border-radius: 20px; background: #fff; box-shadow: 0 24px 60px rgba(11, 45, 90, .28); }
        .cmx-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 16px 20px 12px; border-bottom: 1px solid #e8f0f7; }
        .cmx-head h3 { margin: 6px 0 0; font-size: 16px; font-weight: 800; color: #1e3a5f; }
        .cmx-x { display: grid; place-items: center; width: 32px; height: 32px; flex: none; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; color: #64748b; cursor: pointer; }
        .cmx-x:hover { background: #f1f5f9; }
        .cmx-x svg { width: 16px; height: 16px; }
        .cmx-body { flex: 1 1 auto; overflow-y: auto; padding: 14px 20px; display: grid; gap: 12px; }
        .cmx-who { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 6px 16px; padding: 10px 12px; border: 1px solid #e8f0f7; border-radius: 14px; background: #f8fbfe; font-size: 12px; color: #475569; }
        .cmx-who b { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
        .cmx-who a { color: #2563eb; text-decoration: none; }
        .cmx-who a:hover { text-decoration: underline; }
        .cmx-msg { margin: 0; padding: 12px 14px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; white-space: pre-line; font-size: 13.5px; line-height: 1.65; color: #334155; }
        .cmx-label { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11.5px; font-weight: 700; color: #475569; }
        .cmx-label svg { width: 14px; height: 14px; color: #94a3b8; }
        .cmx-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; padding: 12px 20px 16px; border-top: 1px solid #e8f0f7; background: #fff; }
        .cmx-foot form { margin: 0; }
    </style>

    <x-ws.table :columns="['Người gửi', 'Nội dung', 'Thời gian', '']" min-width="min-w-[900px]">
        @foreach ($messages as $m)
            <tr x-data="{ open: false }" class="align-top">
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
                    <div class="cmx-meta">
                        <x-ws.badge :tone="$m['statusTone']">
                            <x-lucide :name="$m['statusIcon']" class="h-3 w-3" />{{ $m['statusLabel'] }}
                        </x-ws.badge>
                        <x-ws.badge :tone="$m['topicTone']">{{ $m['topic'] }}</x-ws.badge>
                        @if ($m['ticket'] ?? null)
                            <span class="cmx-ticket"><x-lucide name="ticket" />{{ $m['ticket'] }}</span>
                        @endif
                    </div>
                    <p class="line-clamp-2 whitespace-pre-line text-[13px] leading-relaxed text-slate-600">{{ $m['message'] }}</p>
                    <button type="button" @click="open = true" class="cmx-more">
                        <x-lucide name="maximize-2" />{{ $schemaReady ? 'Xem chi tiết & ghi chú' : 'Xem chi tiết' }}
                    </button>
                    @if ($m['note'])
                        <p class="mt-1.5 flex items-start gap-1 text-[11px] italic text-slate-400">
                            <x-lucide name="sticky-note" class="mt-0.5 h-3 w-3 shrink-0" />{{ \Illuminate\Support\Str::limit($m['note'], 60) }}
                        </p>
                    @endif

                    <template x-teleport="body">
                        <div class="cmx-back" x-show="open" x-cloak x-transition.opacity.duration.150ms
                             @click.self="open = false" @keydown.escape.window="open = false">
                            <section class="cmx-dlg" role="dialog" aria-modal="true" aria-labelledby="cmx-title-{{ $m['id'] }}">
                                <header class="cmx-head">
                                    <div>
                                        <div class="cmx-meta" style="margin:0">
                                            <x-ws.badge :tone="$m['statusTone']">
                                                <x-lucide :name="$m['statusIcon']" class="h-3 w-3" />{{ $m['statusLabel'] }}
                                            </x-ws.badge>
                                            <x-ws.badge :tone="$m['topicTone']">{{ $m['topic'] }}</x-ws.badge>
                                        </div>
                                        <h3 id="cmx-title-{{ $m['id'] }}">Phiếu {{ $m['ticket'] ?? '#'.$m['id'] }}</h3>
                                    </div>
                                    <button type="button" class="cmx-x" @click="open = false" aria-label="Đóng"><x-lucide name="x" /></button>
                                </header>

                                <div class="cmx-body">
                                    <div class="cmx-who">
                                        <div><b>Người gửi</b>{{ $m['name'] }}</div>
                                        <div><b>Email</b><a href="mailto:{{ $m['email'] }}">{{ $m['email'] }}</a></div>
                                        @if ($m['phone'])
                                            <div><b>Điện thoại</b><a href="tel:{{ preg_replace('/[^0-9+]/', '', $m['phone']) }}">{{ $m['phone'] }}</a></div>
                                        @endif
                                        <div><b>Tài khoản</b>
                                            @if ($m['accountUrl'])<a href="{{ $m['accountUrl'] }}">{{ $m['accountName'] }}</a>@else Khách chưa đăng nhập @endif
                                        </div>
                                        <div><b>Gửi lúc</b>{{ $m['createdAt'] }} · {{ $m['createdAgo'] }}</div>
                                        @if ($m['handledBy'])
                                            <div><b>Người xử lý</b>{{ $m['handledBy'] }} · {{ $m['handledAt'] }}</div>
                                        @endif
                                    </div>

                                    <div>
                                        <p class="cmx-label"><x-lucide name="mail" />Nội dung</p>
                                        <p class="cmx-msg">{{ $m['message'] }}</p>
                                    </div>

                                    @if ($schemaReady)
                                        <form method="POST" action="{{ route('admin.contact-messages.note', $m['id']) }}" class="space-y-2">
                                            @csrf
                                            <label for="note-{{ $m['id'] }}" class="cmx-label">
                                                <x-lucide name="sticky-note" />Ghi chú nội bộ — chỉ quản trị viên đọc, không gửi cho người gửi phiếu
                                            </label>
                                            <textarea id="note-{{ $m['id'] }}" name="admin_note" rows="3" maxlength="2000" class="admin-input"
                                                      placeholder="Đã gọi lúc 9h, hẹn gửi lại ảnh màn hình...">{{ $m['note'] }}</textarea>
                                            <x-ws.btn type="submit" size="sm" variant="ghost" icon="save">Lưu ghi chú</x-ws.btn>
                                        </form>
                                    @endif
                                </div>

                                <footer class="cmx-foot">
                                    <x-ws.btn :href="$m['mailto']" size="sm" variant="soft" icon="send">Trả lời qua email</x-ws.btn>
                                    @if ($m['status'] === 'new')
                                        <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="in_progress">
                                            <x-ws.btn type="submit" size="sm" variant="ghost" icon="clock-3">Nhận xử lý</x-ws.btn>
                                        </form>
                                    @endif
                                    @unless ($m['resolved'])
                                        <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="resolved">
                                            <x-ws.btn type="submit" size="sm" variant="success" icon="check-circle-2">Đã xử lý</x-ws.btn>
                                        </form>
                                    @endunless
                                    @if ($m['open'])
                                        <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="spam">
                                            <x-ws.btn type="submit" size="sm" variant="danger" icon="ban">Đánh dấu rác</x-ws.btn>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.contact-messages.status', $m['id']) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="new">
                                            <x-ws.btn type="submit" size="sm" variant="ghost" icon="rotate-ccw">Mở lại</x-ws.btn>
                                        </form>
                                    @endif
                                </footer>
                            </section>
                        </div>
                    </template>
                </td>

                <td class="px-4 py-3">
                    <p class="text-[12px] font-medium text-slate-600">{{ $m['createdAt'] }}</p>
                    <p class="text-[11px] text-slate-400">{{ $m['createdAgo'] }}</p>
                    @if ($m['handledBy'])
                        <p class="cmx-handled">Xử lý: {{ $m['handledBy'] }}<br>{{ $m['handledAt'] }}</p>
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

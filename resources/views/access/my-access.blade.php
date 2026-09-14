{{--
  Route: access.myAccess | Frame: ACC-07 "Quyền của tôi"
  Spec: 7.3 — phân biệt Đang có quyền / Sắp hết hạn / Đã hết hạn +
  lịch sử; hết hạn vẫn xem được lịch sử nộp/điểm/kết quả cũ.
  Dữ liệu thật do App\Http\Controllers\Access\AccessController::myAccess() truyền vào qua
  App\Services\Access\AccessService::myAccessData().

  SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN, KHÔNG ĐỔI LOGIC:
    · Trước đây @extends('layouts.student') nên giáo viên / phụ huynh mở trang này cũng bị
      đưa vào vỏ khu học sinh. Giờ khung khu làm việc chọn theo đúng vai trò người đang
      đăng nhập (như Ví token, Kích hoạt mã, Lịch sử đặt mua).
    · Dựng lại theo bộ thẻ x-ws.*; vẫn nguyên $tab / $tabs / $rights và mọi khoá bên trong.
--}}
@php
    $accessUser = auth()->user();
    $accessRole = match (true) {
        (bool) $accessUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin',
        (bool) $accessUser?->hasRole(\App\Models\Role::TEACHER) => 'teacher',
        (bool) $accessUser?->hasRole(\App\Models\Role::PARENT) => 'parent',
        default => 'student',
    };
@endphp
@extends('layouts.workspace', ['wsRole' => $accessRole])

@section('title', 'Quyền của tôi')
@section('page-title', 'Quyền của tôi')

@section('content')
    @php
        $tab = $tab ?? 'active';
        $tabs = $tabs ?? [];
        $rights = $rights ?? [];

        /* Số ở 3 ô thống kê lấy thẳng từ $tabs — cùng một nguồn đếm, không truy vấn thêm. */
        $countOf = function (string $label) use ($tabs) {
            foreach ($tabs as $t) {
                if (($t['label'] ?? '') === $label) {
                    return (int) ($t['count'] ?? 0);
                }
            }

            return 0;
        };
    @endphp

    {{-- SỬA 25/8: flash sau khi kích hoạt mã thành công (access.activate.store), xem
         App\Http\Controllers\Access\AccessController::activateStore(). --}}
    @if (session('status') === 'code-activated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Kích hoạt thành công — quyền đã có hiệu lực ngay bây giờ.'])
    @endif

    <x-ws.page-header title="Quyền truy cập của tôi" icon="shield-check"
                      subtitle="Hết hạn vẫn xem được lịch sử nộp, điểm và kết quả cũ — chỉ không đọc, làm hay nộp mới nội dung được bảo vệ.">
        <x-slot:actions>
            <x-ws.btn :href="route('access.activate')" variant="onhero" icon="key-round">Kích hoạt mã</x-ws.btn>
            {{-- SỬA 25/8 (2): "lưu lại lịch sử đặt mua có học sinh luôn" — liên kết sang
                 access.history (liệt kê Order thô, khác trang này chỉ hiện AccessRight). --}}
            <x-ws.btn :href="route('access.history')" variant="onhero-ghost" icon="receipt-text">Lịch sử đặt mua</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ws.stat label="Đang có quyền" :value="$countOf('Đang có quyền')" tone="emerald" icon="check-circle-2"
                   hint="Dùng được bình thường" />
        <x-ws.stat label="Sắp hết hạn" :value="$countOf('Sắp hết hạn')"
                   :tone="$countOf('Sắp hết hạn') > 0 ? 'amber' : 'neutral'" icon="clock-3"
                   :hint="$countOf('Sắp hết hạn') > 0 ? 'Nên gia hạn trước khi hết' : 'Chưa có quyền nào sắp hết'" />
        <x-ws.stat label="Đã hết hạn" :value="$countOf('Đã hết hạn')" tone="neutral" icon="ban"
                   hint="Vẫn xem lại được kết quả cũ" />
    </div>

    <x-ws.tabs :tabs="$tabs" />

    @if (count($rights) === 0)
        <x-ws.empty-state icon="shield-check" title="Không có quyền nào ở trạng thái này"
                          description="Kích hoạt mã hoặc đặt học liệu để mở quyền truy cập."
                          action-label="Kích hoạt mã" :action-href="route('access.activate')" />
    @else
        <div class="space-y-3">
            @foreach ($rights as $r)
                <x-ws.card padding="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border border-sky-100 bg-[#F8FBFE] text-blue-600">
                                <x-lucide name="wallet-cards" class="h-4.5 w-4.5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-[14px] font-bold text-slate-800">{{ $r['title'] }}</p>
                                <p class="mt-0.5 inline-flex items-center gap-1 text-[11px] text-slate-400">
                                    <x-lucide name="calendar-days" class="h-3 w-3" />Hết hạn: {{ $r['expires'] }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2.5">
                            <x-ws.badge :tone="$r['tone']">{{ $r['status'] }}</x-ws.badge>
                            <x-ws.btn :href="route('materials.show', $r['productId'] ?? 1)" size="sm" variant="soft" icon="refresh-cw">Gia hạn</x-ws.btn>
                        </div>
                    </div>
                </x-ws.card>
            @endforeach
        </div>
    @endif
@endsection

{{--
  Route: access.activate | Frame: ACC-02
  Spec: 7.4 (thời hạn bắt đầu khi kích hoạt mã hợp lệ; mã sai scope không
  tự chuyển đổi).
  SỬA 25/8: form submit thật (POST access.activate.store) — xem
  App\Services\Access\AccessService::activateCode(), tái dùng nguyên vẹn
  App\Services\OrderActivationService::activate() đã có sẵn.

  SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN, KHÔNG ĐỔI LOGIC:
    · Trước đây trang này @extends('layouts.guest') nên bấm "Nhập mã kích hoạt quyền" trong
      menu tài khoản là văng ra vỏ trang công khai. Giờ dùng chung khung khu làm việc, vai
      trò lấy theo đúng người đang đăng nhập (giống trang Ví token).
    · Dựng lại theo bộ thẻ x-ws.* như các màn đã thiết kế; vẫn nguyên hai route cũ và các
      biến $code / $activationCode / $decision do AccessService::activationLookup trả về.
--}}
@php
    $activateUser = auth()->user();
    $activateRole = match (true) {
        (bool) $activateUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin',
        (bool) $activateUser?->hasRole(\App\Models\Role::TEACHER) => 'teacher',
        (bool) $activateUser?->hasRole(\App\Models\Role::PARENT) => 'parent',
        default => 'student',
    };
@endphp
@extends('layouts.workspace', ['wsRole' => $activateRole])

@section('title', 'Kích hoạt mã')
@section('page-title', 'Kích hoạt mã')

@section('content')
    @php
        $code = $code ?? null;
        $decision = $decision ?? null;
        $activationCode = $activationCode ?? null;
    @endphp

    <x-ws.page-header title="Kích hoạt mã" icon="key-round"
                      :back="route('access.myAccess')" back-label="Quyền truy cập của tôi"
                      subtitle="Nhập mã in trên thẻ hoặc mã được cấp sau khi đặt đơn. Thời hạn quyền bắt đầu tính từ lúc kích hoạt — không phải lúc đặt đơn.">
        <x-slot:actions>
            <x-ws.btn :href="route('access.myAccess')" variant="onhero-ghost" icon="shield-check">Quyền của tôi</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.3fr_1fr]">

        {{-- ══════ Ô nhập mã ══════ --}}
        <x-ws.card title="Nhập mã kích hoạt" icon="key-round">
            <form method="POST" action="{{ route('access.activate.store') }}" class="space-y-4">
                @csrf

                <x-ws.field label="Mã kích hoạt" name="code" required
                            hint="Gõ đúng cả dấu gạch nối. Mã không phân biệt chữ hoa hay thường.">
                    <input id="code" name="code" type="text" maxlength="60" required autocomplete="off"
                           value="{{ old('code', $code ?? '') }}" placeholder="OT360-XXXX-XXXX"
                           class="admin-input text-center font-mono text-base font-bold uppercase tracking-[0.18em] {{ $errors->has('code') ? 'is-invalid' : '' }}">
                </x-ws.field>

                <x-ws.btn type="submit" variant="primary" icon="key-round" size="lg" class="w-full">Kích hoạt mã</x-ws.btn>
            </form>

            {{-- Kết quả lần bấm gần nhất, hoặc xem trước khi vào bằng link có sẵn ?code=... --}}
            @if ($errors->any())
                <div class="mt-4 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-3.5">
                    <x-ws.icon-tile icon="alert-triangle" tone="rose" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-bold text-rose-700">Chưa kích hoạt được</p>
                        <p class="mt-0.5 text-[12px] leading-relaxed text-rose-700">{{ implode(' ', $errors->all()) }}</p>
                    </div>
                </div>
            @elseif ($decision !== null && ! $decision->allowed)
                {{-- Xem trước lý do khi trang được mở qua link có sẵn ?code=... (vd link trong
                     email báo mã) — TRƯỚC khi bấm Kích hoạt, chưa phải kết quả submit. --}}
                <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5">
                    <x-ws.icon-tile icon="alert-triangle" tone="amber" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-bold text-amber-800">Mã này chưa dùng được</p>
                        <p class="mt-0.5 text-[12px] leading-relaxed text-amber-800">{{ $decision->message }}</p>
                    </div>
                </div>
            @elseif ($decision !== null && $decision->allowed)
                <div class="mt-4 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-3.5">
                    <x-ws.icon-tile icon="check-circle-2" tone="emerald" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-bold text-emerald-800">Mã hợp lệ</p>
                        <p class="mt-0.5 text-[12px] leading-relaxed text-emerald-800">
                            Bấm <strong>Kích hoạt mã</strong> để bắt đầu tính thời hạn quyền.
                        </p>
                    </div>
                </div>
            @endif
        </x-ws.card>

        {{-- ══════ Giải thích ══════ --}}
        <div class="space-y-4">
            <x-ws.card title="Kích hoạt hoạt động thế nào" icon="info">
                <ol class="space-y-3">
                    @foreach ([
                        ['Nhập mã', 'Mã in trên thẻ, hoặc mã hệ thống cấp sau khi đơn của bạn được duyệt.'],
                        ['Bấm kích hoạt', 'Thời hạn quyền bắt đầu tính từ đúng lúc này, không phải lúc đặt đơn.'],
                        ['Dùng học liệu', 'Quyền hiện ngay ở mục "Quyền truy cập của tôi".'],
                    ] as $i => $step)
                        <li class="flex items-start gap-2.5">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-blue-50 text-[11px] font-black text-blue-700">{{ $i + 1 }}</span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold text-slate-700">{{ $step[0] }}</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">{{ $step[1] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-4 rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3">
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        <strong class="text-slate-700">Mỗi mã chỉ dùng được một lần</strong> và chỉ mở đúng phần học liệu
                        gắn với mã đó — mã của gói này không tự chuyển sang gói khác.
                    </p>
                </div>
            </x-ws.card>

            <x-ws.card title="Chưa có mã?" icon="wallet-cards">
                <p class="text-[12px] leading-relaxed text-slate-500">
                    Mã kích hoạt được cấp sau khi đơn đặt học liệu của bạn được duyệt. Xem lại đơn đã đặt,
                    hoặc nạp token để đặt đơn mới.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-ws.btn :href="route('access.history')" variant="ghost" icon="history">Lịch sử đặt mua</x-ws.btn>
                    <x-ws.btn :href="route('wallet.index')" variant="soft" icon="wallet-cards">Ví token</x-ws.btn>
                </div>
            </x-ws.card>
        </div>
    </div>
@endsection

{{--
  Route: access.activate | Frame: ACC-02
  Spec: 7.4 (thời hạn bắt đầu khi kích hoạt mã hợp lệ; mã sai scope không
  tự chuyển đổi).
  SỬA 25/8: form submit thật (POST access.activate.store) — xem
  App\Services\Access\AccessService::activateCode(), tái dùng nguyên vẹn
  App\Services\OrderActivationService::activate() đã có sẵn.
--}}
@extends('layouts.guest')

@section('title', 'Kích hoạt mã')

@section('content')
    @php
        $code = $code ?? null;
        $decision = $decision ?? null;
    @endphp

    <div class="max-w-md mx-auto px-4 py-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
            <div class="text-4xl mb-3">🔑</div>
            <h1 class="text-lg font-semibold text-slate-800 mb-1">Kích hoạt mã</h1>
            <p class="text-sm text-slate-500 mb-6">Thời hạn quyền bắt đầu tính từ lúc kích hoạt — không phải lúc đặt đơn</p>

            <form method="POST" action="{{ route('access.activate.store') }}" class="space-y-4 text-left">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Mã kích hoạt</label>
                    <input type="text" name="code" value="{{ old('code', $code ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-3 font-mono text-center tracking-widest"
                           placeholder="OT360-XXXX-XXXX">
                </div>
                <button type="submit" class="w-full px-5 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">Kích hoạt</button>
            </form>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-rose-50 border border-rose-100 p-3 text-xs text-rose-700 text-left">
                    {{ implode(' ', $errors->all()) }}
                </div>
            @elseif ($decision !== null && ! $decision->allowed)
                {{-- Xem trước lý do khi trang được mở qua link có sẵn ?code=... (vd link trong
                     email báo mã) — TRƯỚC khi bấm Kích hoạt, chưa phải kết quả submit. --}}
                <div class="mt-4 rounded-lg bg-amber-50 border border-amber-100 p-3 text-xs text-amber-700 text-left">
                    {{ $decision->message }}
                </div>
            @endif
        </div>
    </div>
@endsection

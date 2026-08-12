{{--
  Route: access.blocked | Frame: ACC-08 "Bài khóa"
  Spec: 2.2 nguyên tắc 1 (nêu đúng lý do trước khi kêu gọi hành động) +
  7.3 (3 cửa độc lập: Thành viên/lớp, Quyền học cá nhân, Tiến độ chung —
  khi thiếu cả quyền cá nhân và tiến độ, ưu tiên giải thích quyền cá nhân
  nhưng vẫn báo bài sẽ theo lịch mở của lớp).
  TODO controller: truyền $gates thật từ App\Services\AccessGateService
  (mỗi gate là 1 App\Support\AccessDecision).
--}}
@extends('layouts.student')

@section('title', 'Bài bị khóa')
@section('page-title', '')

@section('content')
    {{-- $gates do App\Http\Controllers\Access\AccessController truyền vào
    (hiện là placeholder "đã qua" cho tới khi có AccessGateService thật). --}}
    @php
        $gates = $gates ?? [];
        $materialId = $materialId ?? 1;
        $primary = collect($gates)->firstWhere('passed', false);
    @endphp

    <div class="max-w-lg mx-auto py-10 text-center">
        <div class="text-5xl mb-4">🔒</div>
        <h1 class="text-lg font-semibold text-slate-800 mb-2">Bạn chưa mở được bài này</h1>

        @if ($primary)
            <div class="rounded-2xl bg-amber-50 border border-amber-100 p-5 mb-6 text-left">
                <p class="text-sm text-amber-800 font-medium mb-1">{{ $primary['label'] }}</p>
                <p class="text-sm text-amber-700">{{ $primary['message'] }}</p>
                @if (!empty($primary['ctaLabel']))
                    <a href="{{ route($primary['ctaHref'], $materialId) }}" class="inline-block mt-3 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-medium">
                        {{ $primary['ctaLabel'] }}
                    </a>
                @endif
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-5 text-left">
            <p class="text-sm font-medium text-slate-700 mb-3">Chi tiết 3 điều kiện truy cập (7.3)</p>
            <ul class="space-y-2">
                @foreach ($gates as $g)
                    <li class="flex items-start gap-2 text-sm">
                        <span class="{{ $g['passed'] ? 'text-emerald-500' : 'text-slate-300' }}">{{ $g['passed'] ? '✓' : '○' }}</span>
                        <span class="{{ $g['passed'] ? 'text-slate-600' : 'text-slate-400' }}">{{ $g['label'] }} — {{ $g['message'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <a href="{{ route('student.classes.show', 10) }}" class="inline-block mt-6 text-sm text-slate-500">‹ Quay lại lộ trình lớp</a>
    </div>
@endsection

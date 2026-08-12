{{--
  Route: teacher.dashboard | Frame: TEA-01
  Spec: 10.2 — dashboard giáo viên: lớp sắp dạy, bài cần mở, tỷ lệ hoàn
  thành, học sinh cần chú ý, quyền dạy sắp hết hạn, thông báo.
  TODO controller: truyền dữ liệu thật; quyền dạy sắp hết hạn nối
  App\Services\AccessGateService / AccessRight.
--}}
@extends('layouts.teacher')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Teacher\DashboardController truyền vào. --}}
    @php
        $name = $name ?? (auth()->user()->name ?? 'thầy/cô');
        $upcoming = $upcoming ?? [];
        $toOpen = $toOpen ?? [];
        $attentionStudents = $attentionStudents ?? [];
        $accessExpiring = $accessExpiring ?? null;
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-emerald-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm text-sky-600 font-medium">Chào thầy/cô 👋</p>
            <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, hôm nay có {{ count($upcoming) }} buổi dạy</h2>
            <p class="text-sm text-slate-500 mt-1">{{ count($toOpen) }} bài đang chờ mở tiến độ · {{ count($attentionStudents) }} học sinh cần chú ý</p>
        </div>
        <div class="w-16 h-16 rounded-2xl bg-white/70 flex items-center justify-center text-4xl shrink-0 shadow-sm">🍎</div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-tile label="Buổi dạy sắp tới" :value="count($upcoming)" hint="Lịch dạy gần nhất" tone="{{ count($upcoming) > 0 ? 'success' : 'neutral' }}" />
        <x-stat-tile label="Bài chờ mở tiến độ" :value="count($toOpen)" hint="Cần bạn xử lý" tone="{{ count($toOpen) > 0 ? 'warning' : 'neutral' }}" />
        <x-stat-tile label="Học sinh cần chú ý" :value="count($attentionStudents)" hint="Theo dõi sát hơn" tone="{{ count($attentionStudents) > 0 ? 'danger' : 'neutral' }}" />
    </div>

    @if ($accessExpiring)
        <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4 mb-6 flex items-center gap-4 flex-wrap">
            <x-icon-tile emoji="⏳" tone="amber" />
            <p class="text-sm text-amber-800 flex-1">
                Quyền dạy "<strong>{{ $accessExpiring['product'] }}</strong>" sắp hết hạn — còn {{ $accessExpiring['daysLeft'] }} ngày. Hết hạn sẽ không gắn/mở mới được học liệu này ở bất kỳ lớp nào (7.2).
            </p>
            <a href="{{ route('materials.show', 1) }}" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-medium shrink-0">Gia hạn ngay</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>📅</span> Lịch sắp dạy</h3>
                <ul class="space-y-2">
                    @forelse ($upcoming as $u)
                        <li class="flex items-center gap-3 text-sm rounded-xl px-3 py-2.5 hover:bg-slate-50">
                            <div class="w-20 shrink-0 rounded-lg bg-sky-50 text-sky-600 text-xs font-semibold text-center py-1.5">{{ $u['time'] }}</div>
                            <div>
                                <p class="text-slate-700 font-medium">{{ $u['class'] }}</p>
                                <p class="text-xs text-slate-400">{{ $u['topic'] }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400 px-3 py-2">Chưa có buổi dạy nào sắp tới.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>📘</span> Bài cần mở tiến độ</h3>
                <ul class="divide-y divide-slate-100">
                    @forelse ($toOpen as $t)
                        <li class="flex items-center justify-between gap-3 py-3 text-sm">
                            <div class="flex items-center gap-3">
                                <x-icon-tile emoji="📘" tone="violet" />
                                <div>
                                    <p class="text-slate-700 font-medium">{{ $t['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $t['class'] }}{{ $t['chapter'] ? ' · '.$t['chapter'] : '' }}</p>
                                </div>
                            </div>
                            <button type="button" class="text-rose-600 font-medium text-sm shrink-0">Mở ngay ›</button>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400 py-3">Không có bài nào đang chờ mở.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🧑‍🎓</span> Học sinh cần chú ý</h3>
            <ul class="space-y-3">
                @forelse ($attentionStudents as $s)
                    <li class="flex items-center gap-3 text-sm">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($s['name']) }}&background=fecdd3&color=be123c&size=64&bold=true"
                             alt="{{ $s['name'] }}" class="w-9 h-9 rounded-full shrink-0">
                        <div>
                            <p class="text-slate-700 font-medium">{{ $s['name'] }}</p>
                            <p class="text-xs text-slate-400">{{ $s['class'] }} · {{ $s['reason'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">Chưa có học sinh nào cần chú ý đặc biệt.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection

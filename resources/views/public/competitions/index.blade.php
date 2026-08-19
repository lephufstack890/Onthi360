@extends('layouts.guest')

@section('title', 'Cuộc thi')

@section('content')
    @php
        $competitions = $competitions ?? [];
    @endphp

    {{-- Hero giới thiệu --}}
    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-rose-900 text-white">
        <div class="max-w-7xl mx-auto px-4 py-14 lg:py-20">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-rose-200 text-xs font-medium mb-4">🏆 Sân đấu học thuật</span>
            <h1 class="text-3xl lg:text-4xl font-semibold leading-tight">Thử thách bản thân<br class="hidden lg:block"> cùng các cuộc thi học thuật</h1>
            <p class="text-slate-300 mt-4 max-w-md">Đề thi luôn thuộc kho Tài liệu — cuộc thi chỉ tham chiếu đề để tổ chức thành sự kiện có thời gian và bảng xếp hạng riêng.</p>
            <div class="flex flex-wrap gap-6 mt-6 text-sm">
                <div><p class="text-2xl font-semibold">{{ count($competitions) }}+</p><p class="text-slate-400">cuộc thi/khảo sát đã tổ chức</p></div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10 lg:py-14">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($competitions as $c)
                <a href="{{ route('competitions.show', $c['id']) }}" class="group block rounded-2xl bg-white border border-slate-200 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="aspect-[16/9] bg-slate-100 overflow-hidden relative">
                        <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($c['title']) }}/480/270" alt="" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute top-3 left-3"><x-status-badge :tone="$c['statusTone']">{{ $c['statusLabel'] }}</x-status-badge></div>
                    </div>
                    <div class="p-5">
                        <p class="text-xs font-medium text-rose-600 uppercase tracking-wide">{{ $c['typeLabel'] }}</p>
                        <h3 class="font-semibold text-slate-800 mt-1 line-clamp-2">{{ $c['title'] }}</h3>
                        <p class="text-sm text-slate-500 mt-2">
                            🗓
                            @if ($c['startsAt'] && $c['endsAt'])
                                {{ $c['startsAt']->format('d/m') }} – {{ $c['endsAt']->format('d/m/Y') }}
                            @else
                                Chưa đặt lịch
                            @endif
                        </p>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400">
                            <span>👥 {{ number_format($c['participants']) }} người tham gia</span>
                            @if ($c['statusLabel'] === 'Sắp diễn ra' && $c['startCountdown'])
                                <span class="text-rose-600 font-medium">Còn
                                    @if ($c['startCountdown']['unit'] === 'days')
                                        {{ $c['startCountdown']['days'] }} ngày
                                    @else
                                        {{ $c['startCountdown']['hours'] }} giờ {{ $c['startCountdown']['minutes'] }} phút
                                    @endif
                                </span>
                            @elseif ($c['statusLabel'] === 'Đang diễn ra')
                                <span class="text-emerald-600 font-medium">Đang mở</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có cuộc thi nào" description="Quay lại sau để không bỏ lỡ sự kiện mới." />
                </div>
            @endforelse
        </div>
    </div>
@endsection

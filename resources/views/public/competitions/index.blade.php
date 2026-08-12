{{--
  Route: competitions.index | Frame: PUB-08
  Spec: 11.1 (Cuộc thi vs Khảo sát; trạng thái Sắp diễn ra→Đang diễn ra→
  Chờ công bố→Đã công bố→Lưu trữ).
  TODO controller: truyền $competitions (paginate) thật — hiện là dữ liệu
  minh họa để dựng UI; ảnh bìa dùng picsum.photos tạm (xem x-card-item).
--}}
@extends('layouts.guest')

@section('title', 'Cuộc thi')

@section('content')
    @php
        $competitions = [
            ['title' => 'Cuộc thi Tin học trẻ 2026', 'type' => 'Cá nhân', 'starts' => now()->addDays(9), 'ends' => now()->addDays(14), 'status' => 'Sắp diễn ra', 'tone' => 'info', 'participants' => 482],
            ['title' => 'Giải Toán Tư duy mở rộng', 'type' => 'Đồng đội', 'starts' => now()->subDays(1), 'ends' => now()->addDays(2), 'status' => 'Đang diễn ra', 'tone' => 'success', 'participants' => 1240],
            ['title' => 'Khảo sát mức độ hài lòng Q3', 'type' => 'Khảo sát', 'starts' => now()->subDays(20), 'ends' => now()->subDays(13), 'status' => 'Đã công bố', 'tone' => 'neutral', 'participants' => 356],
            ['title' => 'Olympic Lập trình học sinh 2025', 'type' => 'Cá nhân', 'starts' => now()->subMonths(4), 'ends' => now()->subMonths(4)->addDays(3), 'status' => 'Lưu trữ', 'tone' => 'neutral', 'participants' => 890],
        ];
    @endphp

    {{-- Hero giới thiệu --}}
    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-rose-900 text-white">
        <div class="max-w-7xl mx-auto px-4 py-14 lg:py-20 flex flex-col lg:flex-row items-center gap-10">
            <div class="flex-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-rose-200 text-xs font-medium mb-4">🏆 Sân đấu học thuật</span>
                <h1 class="text-3xl lg:text-4xl font-semibold leading-tight">Thử thách bản thân<br class="hidden lg:block"> cùng các cuộc thi học thuật</h1>
                <p class="text-slate-300 mt-4 max-w-md">Đề thi luôn thuộc kho Tài liệu — cuộc thi chỉ tham chiếu đề để tổ chức thành sự kiện có thời gian, bảng xếp hạng và giải thưởng riêng (4.3, 11.1).</p>
                <div class="flex flex-wrap gap-6 mt-6 text-sm">
                    <div><p class="text-2xl font-semibold">{{ count($competitions) }}+</p><p class="text-slate-400">cuộc thi đã tổ chức</p></div>
                    <div><p class="text-2xl font-semibold">2.9k+</p><p class="text-slate-400">lượt tham gia</p></div>
                </div>
            </div>
            <div class="flex-1 hidden lg:block">
                <img src="https://picsum.photos/seed/onthi360-competitions-hero/560/400" alt="" class="rounded-3xl shadow-2xl object-cover w-full aspect-[4/3]">
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10 lg:py-14">
        {{-- Bộ lọc trạng thái — UI tĩnh, TODO nối filter thật theo query string --}}
        <div class="flex flex-wrap gap-2 mb-8 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">Sắp diễn ra</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">Đang diễn ra</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">Đã công bố</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">Lưu trữ</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($competitions as $i => $c)
                <a href="{{ route('competitions.show', $i + 1) }}" class="group block rounded-2xl bg-white border border-slate-200 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="aspect-[16/9] bg-slate-100 overflow-hidden relative">
                        <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($c['title']) }}/480/270" alt="" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute top-3 left-3"><x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge></div>
                    </div>
                    <div class="p-5">
                        <p class="text-xs font-medium text-rose-600 uppercase tracking-wide">{{ $c['type'] }}</p>
                        <h3 class="font-semibold text-slate-800 mt-1 line-clamp-2">{{ $c['title'] }}</h3>
                        <p class="text-sm text-slate-500 mt-2">🗓 {{ $c['starts']->format('d/m') }} – {{ $c['ends']->format('d/m/Y') }}</p>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400">
                            <span>👥 {{ number_format($c['participants']) }} người tham gia</span>
                            @if ($c['status'] === 'Sắp diễn ra')
                                <span class="text-rose-600 font-medium">Còn {{ now()->diffInDays($c['starts']) }} ngày</span>
                            @elseif ($c['status'] === 'Đang diễn ra')
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

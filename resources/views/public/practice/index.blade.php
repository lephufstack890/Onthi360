{{--
  Route: practice.index | Frame: PUB-07
  Spec: 4.1 + 10.1 (Luyện tập công khai: khách/logged-in/lọc rỗng).
  Dữ liệu thật do App\Http\Controllers\Public\PracticeController truyền vào qua
  App\Services\Public\PracticeService::indexData() — cùng nguồn với tab "Tự luyện" của
  App\Services\Student\PracticeService để không lệch danh sách giữa 2 nơi.
  $it['hasCoding'] (4.1: môn Tin có 2 lối chấm — trắc nghiệm/điền đáp án tự động, code qua
  bộ test riêng) — hiện nhãn "Có lập trình" để học sinh biết trước khi bấm vào làm.

  SỬA 18/8: làm lại giao diện card cho đẹp/nhất quán hơn — khách báo "trang ngoài" (trang
  công khai, chưa đăng nhập) cũng xấu như trang trong. KHÔNG đổi dữ liệu/logic, chỉ đổi
  trình bày: cả card giờ bấm được (đi thẳng vào làm bài nếu đã đăng nhập, hoặc sang trang
  đăng nhập nếu chưa — giữ nguyên đúng 2 nhánh $canTakeDirectly cũ), theo đúng khuôn mẫu
  "group + block rounded-2xl ... hover:shadow-lg hover:-translate-y-0.5" mà các trang công
  khai khác (VD resources/views/public/competitions/index.blade.php) đã dùng, để 2 trang
  không bị lệch phong cách. Thêm dải màu + icon tile (x-icon-tile, cùng component dùng ở
  trang Luyện tập của học sinh) để phân biệt nhanh đề có lập trình hay không bằng mắt.
--}}
@extends('layouts.guest')

@section('title', 'Luyện tập')

@section('content')
    @php
        $items = $items ?? [];
        $canTakeDirectly = $canTakeDirectly ?? false;
        $cardAccent = fn (bool $hasCoding) => $hasCoding
            ? ['tone' => 'amber', 'bar' => 'from-amber-400 to-amber-300']
            : ['tone' => 'emerald', 'bar' => 'from-emerald-400 to-emerald-300'];
    @endphp

    {{-- Hero giới thiệu --}}
    <div class="bg-gradient-to-br from-emerald-50 via-white to-sky-50">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16 flex items-center justify-between flex-wrap gap-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-emerald-600 text-xs font-medium mb-4 shadow-sm">📝 Kho luyện tập công khai</span>
                <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Luyện đến khi thành thạo<br class="hidden lg:block">— chấm tự động ngay khi nộp</h1>
                <p class="text-slate-500 mt-3 max-w-xl">Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3). Ai cũng xem được đề; đăng nhập để nộp bài và lưu lại kết quả.</p>
                <div class="flex flex-wrap gap-6 mt-6 text-sm">
                    <div><p class="text-2xl font-semibold text-slate-800">{{ count($items) }}+</p><p class="text-slate-400">bài luyện tập công khai</p></div>
                </div>
            </div>
            <div class="text-6xl hidden sm:block">🎯</div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse ($items as $it)
                @php $accent = $cardAccent($it['hasCoding'] ?? false); @endphp
                <a href="{{ $canTakeDirectly ? route('student.assessment.take', $it['id']) : route('login') }}"
                   class="group relative flex flex-col h-full rounded-2xl bg-white border border-slate-200 p-5 pt-6 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['bar'] }}"></span>

                    <div class="flex items-center justify-between mb-3">
                        <x-icon-tile emoji="📝" :tone="$accent['tone']" />
                        <div class="flex items-center gap-1.5">
                            @if ($it['hasCoding'] ?? false)
                                <x-status-badge tone="warning">💻 Có lập trình</x-status-badge>
                            @endif
                            <x-status-badge tone="info">{{ $it['itemsCount'] }} câu</x-status-badge>
                        </div>
                    </div>

                    <h3 class="font-semibold text-slate-800 leading-snug line-clamp-2">{{ $it['title'] }}</h3>

                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400">
                        <span>{{ $it['totalPoints'] }} điểm</span>
                        <span>{{ $it['durationMinutes'] ? $it['durationMinutes'].' phút' : 'Không giới hạn' }}</span>
                    </div>

                    <div class="mt-auto pt-4 flex items-center justify-end">
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-rose-600 group-hover:gap-2 transition-all">
                            {{ $canTakeDirectly ? 'Làm bài' : 'Đăng nhập để làm bài' }}
                            <span aria-hidden="true">→</span>
                        </span>
                    </div>
                </a>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có bài luyện tập công khai nào" description="Quay lại sau để xem bài mới." />
                </div>
            @endforelse
        </div>
    </div>
@endsection

@extends('layouts.student')

@section('title', 'Tài liệu của tôi')
@section('page-title', 'Tài liệu của tôi')

{{--
    SỬA 28/8 ("tài liệu mua được chỉ xem trong trang học sinh"): trang này thay cho việc hiện
    Mục lục + Tài nguyên đính kèm ngay trên trang tài liệu công khai (đã bỏ, xem
    public/materials/show.blade.php) — chỉ liệt kê sản phẩm ĐÃ MUA (xem
    App\Services\Student\LibraryService), tab Sách/Chuyên đề/Bộ đề. Học sinh xem được TOÀN
    BỘ file đính kèm TRỪ "PDF hướng dẫn" — cột 'resources' do service lọc sẵn, không có
    'guide' trong đó (luật thật chặn ở AccessService::downloadResource()).
--}}
@section('content')
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
    @endphp

    <x-page-header title="📖 Tài liệu của tôi" subtitle="Sách, chuyên đề, bộ đề bạn đã mua hoặc kích hoạt — tải bài tập và học liệu đi kèm ngay tại đây." />

    <x-tabs :tabs="$tabs" />

    @if (empty($products))
        <x-empty-state title="Chưa có tài liệu nào trong mục này" description="Mua hoặc nhập mã kích hoạt ở trang Tài liệu để bắt đầu." actionLabel="Khám phá tài liệu" :actionHref="route('materials.index')" />
    @else
        <div class="space-y-5">
            @foreach ($products as $p)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 lg:p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-16 h-20 rounded-xl overflow-hidden shrink-0 bg-gradient-to-br from-rose-100 to-sky-50 flex items-center justify-center">
                            @if ($p['coverPath'])
                                <img src="{{ asset('storage/'.$p['coverPath']) }}" alt="Bìa {{ $p['title'] }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-2xl">📘</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-slate-800 leading-snug">{{ $p['title'] }}</h3>
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium mt-1">
                                <span>✓</span> Đã sở hữu
                            </span>
                        </div>
                    </div>

                    @if (count($p['resources']) > 0)
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Tài nguyên đính kèm</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach ($p['resources'] as $res)
                                    <a href="{{ route('access.resource', ['product' => $p['id'], 'kind' => $res['kind']]) }}"
                                       class="flex items-center gap-2 px-3 py-2.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-rose-200 hover:text-rose-600 transition">
                                        <span>{{ $res['icon'] }}</span> {{ $res['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- SỬA 28/8 (2 — "ẩn chỗ mục lục đi không cần cái này đâu, sau cần thì tôi
                         nói sau"): đã ẩn khối "Mục lục" ở đây theo yêu cầu — KHÔNG xoá dữ liệu
                         phía sau ($p['toc'] vẫn được LibraryService tính như cũ, chỉ bỏ hiển
                         thị), cần lại thì chỉ việc thêm lại đúng khối này. --}}

                    {{-- SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — bài tập lập trình chấm kiểu
                         thi online (tái dùng Student\PracticeByQuestionService). Xem
                         LibraryService::exercisesFor(); "Làm bài" mở phiên luyện CHỈ bài này
                         qua startExercise() (kiểm tra lại quyền sở hữu sản phẩm ở đó, không tin
                         riêng việc trang này chỉ hiện sản phẩm đã mua). --}}
                    @if (count($p['exercises']) > 0)
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">🧪 Bài tập</p>
                            <div class="space-y-2">
                                @foreach ($p['exercises'] as $ex)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-slate-200">
                                        <div class="min-w-0">
                                            <p class="text-sm text-slate-700 truncate">{{ $ex['title'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $ex['points'] }} điểm · {{ $ex['summary'] }}</p>
                                        </div>
                                        <form action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" method="POST" class="shrink-0">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ url()->full() }}">
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium hover:bg-rose-700 transition">Làm bài ›</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Bài tập chưa có chấm tự động — bài làm sẽ được ghi nhận, chưa báo đúng/sai ngay.</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @push('scripts')
        <style>
            [x-cloak] { display: none !important; }
        </style>
    @endpush
@endsection

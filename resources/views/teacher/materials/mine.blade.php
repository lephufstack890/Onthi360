@extends('layouts.teacher')

@section('title', 'Tài liệu của tôi')
@section('page-title', 'Tài liệu của tôi')

{{--
    SỬA 28/8 (2 — "bên giáo viên cũng xem tài liệu giống như học sinh, chỉ khác được xem
    thêm file hướng dẫn"): y hệt resources/views/student/materials/mine.blade.php — chỉ khác
    ở chỗ Teacher\LibraryController gọi LibraryService::indexData(..., includeGuide: true)
    nên $p['resources'] có thêm mục "PDF hướng dẫn" mà bên học sinh không có. Mục lục cũng ẩn
    ở đây, giống bên học sinh (xem ghi chú "ẩn chỗ mục lục đi" ở bản học sinh).
--}}
@section('content')
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
    @endphp

    <x-page-header title="📖 Tài liệu của tôi" subtitle="Sách, chuyên đề, bộ đề bạn đã mua hoặc kích hoạt — tải bài tập, học liệu đi kèm và PDF hướng dẫn ngay tại đây." />

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

                    {{-- SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — giáo viên CHỈ xem/tải đề
                         bài để tham khảo, KHÔNG có nút "Làm bài" (mục này chỉ Admin quản lý
                         thêm/sửa/xoá — giáo viên không quản lý; và luồng "Làm bài" tương tác là
                         luồng riêng dành cho học sinh, xem
                         Student\PracticeByQuestionService::startForQuestion() — bên giáo viên
                         không có UI làm bài tương ứng). Xem LibraryService::exercisesFor(). --}}
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
                                        <a href="{{ route('access.resource.exerciseAttachment', [$p['id'], $ex['id'], 'statement']) }}" class="text-xs text-rose-600 font-medium shrink-0">Xem đề bài</a>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Học sinh làm bài trực tiếp (kiểu thi online) ở trang "Tài liệu của tôi" của các em.</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection

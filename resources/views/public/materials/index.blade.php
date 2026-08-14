{{--
  Route: materials.index | Frame: PUB-05
  Spec: 4.1/4.3 (Tài liệu = tabs Sách/Chuyên đề/Đề thi; Đề thi không là menu riêng).
  Dữ liệu thật do App\Http\Controllers\Public\MaterialController truyền vào qua
  App\Services\Public\MaterialService::indexData() — ảnh bìa dùng picsum.photos tạm (x-card-item).
--}}
@extends('layouts.guest')

@section('title', 'Tài liệu')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $materials = $materials ?? [];
    @endphp

    <div class="bg-gradient-to-br from-amber-50 via-white to-rose-50">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-amber-600 text-xs font-medium mb-4 shadow-sm">📖 Kho tài liệu</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Sách, chuyên đề và đề thi<br class="hidden lg:block">được biên soạn kỹ, luôn nêu rõ điều kiện</h1>
            <p class="text-slate-500 mt-3 max-w-xl">Mỗi tài liệu nói rõ Công khai/Có phí/Cần kích hoạt ngay trên thẻ — không giấu điều kiện sau nút bấm (12.2). Sách mặc định bản mềm, có thể chọn mua kèm bản in.</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-tabs :tabs="$tabs" />

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @forelse ($materials as $m)
                <x-card-item :title="$m['title']" :meta="$m['meta']" :average="$m['average']" :count="$m['count']"
                             href="{{ route('materials.show', $m['id']) }}" :badgeLabel="$m['badge']" :badgeTone="$m['tone']" />
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có tài liệu trong danh mục này" description="Thử chọn danh mục khác ở trên." />
                </div>
            @endforelse
        </div>
    </div>
@endsection

{{--
  Route: materials.index | Frame: PUB-05
  Spec: 4.1/4.3 (Tài liệu = tabs Sách/Chuyên đề/Đề thi; Đề thi không là menu riêng).
  TODO controller: truyền $materials theo tab (product type) — hiện là dữ
  liệu minh họa để dựng UI; ảnh bìa dùng picsum.photos tạm (x-card-item).
--}}
@extends('layouts.guest')

@section('title', 'Tài liệu')

@section('content')
    @php
        $tab = request('tab', 'sach');
        $tabs = [
            ['label' => '📘 Sách', 'href' => route('materials.index'), 'active' => $tab === 'sach', 'count' => 42],
            ['label' => '🗂️ Chuyên đề', 'href' => route('materials.index', ['tab' => 'chuyen-de']), 'active' => $tab === 'chuyen-de', 'count' => 18],
            ['label' => '📝 Đề thi', 'href' => route('materials.index', ['tab' => 'de-thi']), 'active' => $tab === 'de-thi', 'count' => 36],
        ];
        $materials = [
            ['title' => 'Sách: Ôn thi Tin học 10', 'meta' => 'Bản mềm + bản in · 199.000đ', 'average' => 4.7, 'count' => 88, 'badge' => 'Cần kích hoạt', 'tone' => 'warning'],
            ['title' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao', 'meta' => 'Cần kích hoạt · 349.000đ', 'average' => null, 'count' => 2, 'badge' => 'Cần kích hoạt', 'tone' => 'warning'],
            ['title' => 'Sách: Toán tư duy lớp 9', 'meta' => 'Bản mềm + bản in · 179.000đ', 'average' => 4.9, 'count' => 154, 'badge' => 'Bán chạy', 'tone' => 'success'],
            ['title' => 'Đề thi: Thử sức trước kỳ thi HK1', 'meta' => 'Miễn phí · Công khai', 'average' => 4.5, 'count' => 320, 'badge' => 'Công khai', 'tone' => 'info'],
        ];
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
                             href="{{ route('materials.show', 1) }}" :badgeLabel="$m['badge']" :badgeTone="$m['tone']" />
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có tài liệu trong danh mục này" description="Thử chọn danh mục khác ở trên." />
                </div>
            @endforelse
        </div>
    </div>
@endsection

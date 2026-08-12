{{--
  Route: materials.index | Frame: PUB-05
  Spec: 4.1/4.3 (Tài liệu = tabs Sách/Chuyên đề/Đề thi; Đề thi không là menu riêng).
  TODO controller: truyền $materials theo tab (product type).
--}}
@extends('layouts.guest')

@section('title', 'Tài liệu')

@section('content')
    @php
        $tab = request('tab', 'sach');
        $tabs = [
            ['label' => 'Sách', 'href' => route('materials.index'), 'active' => $tab === 'sach', 'count' => 42],
            ['label' => 'Chuyên đề', 'href' => route('materials.index', ['tab' => 'chuyen-de']), 'active' => $tab === 'chuyen-de', 'count' => 18],
            ['label' => 'Đề thi', 'href' => route('materials.index', ['tab' => 'de-thi']), 'active' => $tab === 'de-thi', 'count' => 36],
        ];
        $materials = [
            ['title' => 'Sách: Ôn thi Tin học 10', 'meta' => 'Bản mềm + bản in · 199.000đ', 'average' => 4.7, 'count' => 88],
            ['title' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao', 'meta' => 'Cần kích hoạt · 349.000đ', 'average' => null, 'count' => 2],
        ];
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-page-header title="Tài liệu" subtitle="Sách mặc định bản mềm, có thể chọn mua kèm bản in (7.4)." />

        <x-tabs :tabs="$tabs" />

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse ($materials as $m)
                <x-card-item :title="$m['title']" :meta="$m['meta']" :average="$m['average']" :count="$m['count']"
                             href="{{ route('materials.show', 1) }}" badgeLabel="Cần kích hoạt" badgeTone="warning" />
            @empty
                <x-empty-state title="Chưa có tài liệu trong danh mục này" />
            @endforelse
        </div>
    </div>
@endsection

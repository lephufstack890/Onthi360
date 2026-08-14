{{--
  Route: parent.results.index
  Spec: 10.3 ("tiến độ, kết quả mới công bố") — mục điều hướng cấp cao trong sidebar phụ
  huynh, không gắn với 1 con cụ thể. App\Http\Controllers\Parent\ResultController tự chuyển
  thẳng tới tab "Kết quả & Tiến độ" của parent.children.show nếu phụ huynh chỉ có đúng 1 con
  đã xác minh — trang này CHỈ hiện khi có từ 2 con đã xác minh trở lên.
--}}
@extends('layouts.parent')

@section('title', 'Kết quả & Tiến độ')
@section('page-title', 'Kết quả & Tiến độ')

@section('content')
    @php
        $children = $children ?? [];
    @endphp

    <x-page-header title="Kết quả & Tiến độ" subtitle="Chọn con để xem kết quả và tiến độ chi tiết." />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse ($children as $c)
            <a href="{{ route('parent.children.show', ['child' => $c['id'], 'tab' => 'results']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md transition block">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 flex items-center justify-center font-medium text-slate-700">
                        {{ mb_substr($c['name'], 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-700">{{ $c['name'] }}</p>
                        <p class="text-xs text-slate-400">Lớp {{ $c['class'] }}</p>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa có con đã xác minh" description="Liên kết và chờ admin xác minh con trước để xem kết quả, tiến độ." actionLabel="Con của tôi" :actionHref="route('parent.children.index')" />
            </div>
        @endforelse
    </div>
@endsection

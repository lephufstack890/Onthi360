@extends('layouts.student')

@section('title', 'Khóa học của tôi')
@section('page-title', 'Khóa học của tôi')

@section('content')
    @php
        $classes = $classes ?? [];
    @endphp

    <x-ws.page-header title="Khóa học của tôi" icon="book-open" subtitle="Lớp là nơi tổ chức lịch, học viên và tiến độ của bạn (8.1).">
        <x-slot:actions>
            <a href="{{ route('courses.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">+ Khám phá khóa học mới</a>
        </x-slot:actions>
    </x-ws.page-header>

    @if (session('status') === 'joined-class')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tham gia lớp thành công!'])
    @endif

    <div class="rounded-3xl bg-white border border-sky-100 p-5 mb-6">
        <h2 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span><x-lucide name="ticket" class="h-4 w-4" /></span> Có mã lớp?</h2>
        <p class="text-[13px] text-slate-500 mb-3">Giáo viên cung cấp mã lớp riêng cho từng lớp — nhập đúng mã để tham gia ngay.</p>
        <form method="POST" action="{{ route('student.classes.join') }}" class="flex flex-col sm:flex-row gap-3 max-w-md">
            @csrf
            <input type="text" name="code" placeholder="Ví dụ: 10CT-2026"
                   class="flex-1 rounded-xl border {{ $errors->has('code') ? 'border-blue-300' : 'border-sky-100' }} text-[13px] p-2.5">
            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 hover:bg-blue-700 transition-colors shrink-0">Tham gia lớp</button>
        </form>
        @error('code')
            <p class="text-xs text-blue-500 mt-2">{{ $message }}</p>
        @enderror
    </div>

    @if (empty($classes))
        <x-ws.empty-state title="Bạn chưa tham gia lớp nào" description="Khám phá khóa học phù hợp hoặc nhập mã lớp giáo viên cung cấp ở trên." actionLabel="Khám phá khóa học" :actionHref="route('courses.index')" />
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($classes as $c)
                <a href="{{ route('student.classes.show', $c['id']) }}" class="rounded-3xl bg-white border border-sky-100 p-5 hover:shadow-md hover:border-blue-200 transition block">
                    <div class="flex items-start gap-3">
                        <x-ws.icon-tile icon="graduation-cap" tone="rose" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">{{ $c['course'] }}</p>
                            <h3 class="font-semibold text-slate-800 mt-0.5">{{ $c['class'] }}</h3>
                            <p class="text-xs text-slate-400 mt-1.5">{{ $c['teacher'] }}{{ $c['nextSession'] ? ' · Buổi tới: '.$c['nextSession'] : '' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <x-ws.progress-bar :percent="$c['percent']" label="Tiến độ" tone="brand" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection

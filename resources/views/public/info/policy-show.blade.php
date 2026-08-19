@extends('layouts.guest')

@section('title', $title ?? 'Chính sách')

@section('content')
    @php
        $title = $title ?? '';
        $desc = $desc ?? '';
        $sections = $sections ?? [];
    @endphp

    {{-- Hero --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-sky-50 via-white to-rose-50">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-rose-200/30 blur-3xl"></div>
        <div class="absolute -bottom-16 -left-16 w-64 h-64 rounded-full bg-sky-200/30 blur-3xl"></div>
        <div class="relative max-w-3xl mx-auto px-4 py-12 lg:py-16">
            <a href="{{ route('info.index') }}#chinh-sach" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-rose-600 transition-colors mb-4">
                ‹ Quay lại Thông tin
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-sky-600 text-xs font-medium mb-4 shadow-sm">📜 Chính sách</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">{{ $title }}</h1>
            @if ($desc)
                <p class="text-slate-500 mt-3">{{ $desc }}</p>
            @endif
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 py-10 lg:py-14">
        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($sections as $s)
                <div class="p-6">
                    <h2 class="font-semibold text-slate-800 mb-2">{{ $s['heading'] }}</h2>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $s['body'] }}</p>
                </div>
            @empty
                <div class="p-6 text-center text-slate-400 text-sm">Nội dung đang được cập nhật.</div>
            @endforelse
        </div>

        <div class="mt-6 rounded-2xl bg-slate-50 border border-slate-200 p-5 flex items-center justify-between gap-4 flex-wrap">
            <p class="text-sm text-slate-500">Có câu hỏi về chính sách này? Liên hệ với chúng tôi, chúng tôi sẽ phản hồi sớm nhất có thể.</p>
            <a href="{{ route('info.index') }}#lien-he" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition-colors shrink-0">Liên hệ ngay</a>
        </div>
    </div>
@endsection

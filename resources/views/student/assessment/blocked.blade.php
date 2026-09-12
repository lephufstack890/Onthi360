@extends('layouts.student')

@section('title', 'Không thể vào thi')
@section('page-title', 'Không thể vào thi')

@section('content')
    <div class="max-w-lg mx-auto text-center bg-white rounded-3xl border border-sky-100 p-8 lg:p-10">
        <div class="text-4xl mb-3">🚫</div>
        <h1 class="text-lg font-semibold text-slate-700 mb-2">Chưa thể vào làm bài lúc này</h1>
        <p class="text-[13px] text-slate-500 leading-relaxed">{{ $message }}</p>

        <div class="flex flex-wrap justify-center gap-3 mt-6">
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 hover:bg-blue-700">
                Về trang của tôi
            </a>
            <a href="{{ route('student.practice.index') }}" class="px-4 py-2 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium hover:bg-slate-50">
                Luyện tập đề khác
            </a>
        </div>
    </div>
@endsection

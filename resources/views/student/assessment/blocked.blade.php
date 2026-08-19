@extends('layouts.student')

@section('title', 'Không thể vào thi')
@section('page-title', 'Không thể vào thi')

@section('content')
    <div class="max-w-lg mx-auto text-center bg-white rounded-2xl border border-slate-200 p-8 lg:p-10">
        <div class="text-4xl mb-3">🚫</div>
        <h1 class="text-lg font-semibold text-slate-700 mb-2">Chưa thể vào làm bài lúc này</h1>
        <p class="text-sm text-slate-500 leading-relaxed">{{ $message }}</p>

        <div class="flex flex-wrap justify-center gap-3 mt-6">
            <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                Về trang của tôi
            </a>
            <a href="{{ route('student.practice.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
                Luyện tập đề khác
            </a>
        </div>
    </div>
@endsection

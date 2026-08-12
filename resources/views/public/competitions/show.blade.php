{{--
  Route: competitions.show
  Spec: 11.1 (banner, thời gian, đối tượng, thể lệ, cấu trúc đề, countdown, CTA theo trạng thái, kết quả).
  TODO controller: truyền $competition thật.
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết cuộc thi')

@section('content')
    @php
        $competition = ['title' => 'Cuộc thi Tin học trẻ 2026', 'time' => '20/08 - 25/08/2026', 'status' => 'Sắp diễn ra'];
    @endphp

    <div class="max-w-4xl mx-auto px-4 py-10">
        <a href="{{ route('competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Cuộc thi</a>

        <div class="rounded-2xl bg-gradient-to-r from-rose-100 to-amber-100 p-8 mb-6">
            <x-status-badge tone="info">{{ $competition['status'] }}</x-status-badge>
            <h1 class="text-2xl font-semibold text-slate-800 mt-2">{{ $competition['title'] }}</h1>
            <p class="text-slate-500 mt-1">{{ $competition['time'] }}</p>
            {{-- TODO: countdown thật tới thời điểm bắt đầu --}}
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
            <h2 class="font-medium text-slate-700 mb-2">Thể lệ</h2>
            <p class="text-sm text-slate-500">TODO: đối tượng tham gia, thể lệ, cấu trúc đề.</p>
        </div>

        <a href="{{ route('login') }}" class="inline-block px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">
            Đăng nhập để đăng ký
        </a>
    </div>
@endsection

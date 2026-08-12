{{--
  Route: competitions.index | Frame: PUB-08
  Spec: 11.1 (Cuộc thi vs Khảo sát; trạng thái Sắp diễn ra→...→Lưu trữ).
  TODO controller: truyền $competitions (paginate).
--}}
@extends('layouts.guest')

@section('title', 'Cuộc thi')

@section('content')
    @php
        $competitions = [
            ['title' => 'Cuộc thi Tin học trẻ 2026', 'time' => '20/08 - 25/08/2026', 'status' => 'Sắp diễn ra', 'tone' => 'info'],
            ['title' => 'Khảo sát mức độ hài lòng Q3', 'time' => 'Đã kết thúc', 'status' => 'Đã công bố', 'tone' => 'success'],
        ];
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-page-header title="Cuộc thi" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện (4.3)." />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($competitions as $i => $c)
                <a href="{{ route('competitions.show', $i + 1) }}" class="rounded-2xl bg-white border border-slate-200 p-5">
                    <x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge>
                    <h3 class="font-medium text-slate-800 mt-2">{{ $c['title'] }}</h3>
                    <p class="text-sm text-slate-400 mt-1">{{ $c['time'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
@endsection

{{--
  Route: student.notifications | Frame: STU-11 (phần thông báo)
  TODO controller: truyền $notifications (paginate) thật.
--}}
@extends('layouts.student')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    @php
        $notifications = [
            ['icon' => '🔓', 'tone' => 'emerald', 'text' => 'Giáo viên đã mở Bài 12 - Đệ quy cơ bản', 'time' => '1 giờ trước', 'read' => false],
            ['icon' => '⏰', 'tone' => 'amber', 'text' => 'Quyền học "Chuyên đề CTDL nâng cao" sắp hết hạn (còn 5 ngày)', 'time' => '3 giờ trước', 'read' => false],
            ['icon' => '📅', 'tone' => 'sky', 'text' => 'Lịch buổi học "10CT-2026" đổi sang 20:00 thứ Năm', 'time' => '1 ngày trước', 'read' => true],
            ['icon' => '⭐', 'tone' => 'rose', 'text' => 'Đánh giá của bạn cho lớp 10CT-2026 đã được công bố', 'time' => '3 ngày trước', 'read' => true],
        ];
    @endphp

    <x-page-header title="Thông báo" />

    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
        @forelse ($notifications as $n)
            <div class="flex items-start gap-3 p-4 {{ !$n['read'] ? 'bg-rose-50/40' : '' }}">
                <x-icon-tile :emoji="$n['icon']" :tone="$n['tone']" />
                <div class="flex-1">
                    <p class="text-sm text-slate-700">{{ $n['text'] }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $n['time'] }}</p>
                </div>
                @if (!$n['read'])
                    <span class="w-2 h-2 rounded-full bg-rose-500 mt-2"></span>
                @endif
            </div>
        @empty
            <div class="p-8">
                <x-empty-state title="Chưa có thông báo nào" />
            </div>
        @endforelse
    </div>
@endsection

{{--
  Route: parent.children.show | Frame: PAR-02
  Spec: 10.3 — lịch, điểm danh, tiến độ, kết quả, review lớp (phụ huynh
  đủ điều kiện sau 2 buổi/hoạt động của con — 9.2).
  TODO controller: truyền $child thật + $attendance/$results/$schedule.
--}}
@extends('layouts.parent')

@section('title', 'Chi tiết con')
@section('page-title', 'Chi tiết con')

@section('content')
    @php
        $tab = request('tab', 'overview');
        $childId = request()->route('child', 1);
        $tabDefs = ['overview' => 'Tổng quan', 'schedule' => 'Lịch & Điểm danh', 'results' => 'Kết quả & Tiến độ', 'review' => 'Đánh giá lớp'];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = ['label' => $label, 'href' => route('parent.children.show', ['child' => $childId, 'tab' => $key]), 'active' => $tab === $key];
        }
        $results = [
            ['title' => 'Trắc nghiệm chương 2', 'score' => '9/10', 'tone' => 'success', 'time' => '2 ngày trước'],
            ['title' => 'Đề ôn chương 1', 'score' => '7/10', 'tone' => 'warning', 'time' => '1 tuần trước'],
        ];
        $attendance = [
            ['date' => '05/08/2026', 'status' => 'Có mặt', 'tone' => 'success'],
            ['date' => '30/07/2026', 'status' => 'Có mặt', 'tone' => 'success'],
            ['date' => '23/07/2026', 'status' => 'Vắng có phép', 'tone' => 'warning'],
        ];
    @endphp

    <a href="{{ route('parent.children.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Con của tôi</a>

    <div class="rounded-3xl bg-gradient-to-br from-violet-50 via-white to-rose-50 border border-slate-200 p-6 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 flex items-center justify-center font-medium text-slate-700 text-lg">N</div>
            <div>
                <h1 class="text-lg font-semibold text-slate-800">Nguyễn Minh An</h1>
                <p class="text-sm text-slate-500">Lớp 10CT-2026 · Luyện thi vào 10 Chuyên Tin</p>
            </div>
        </div>
        <div class="w-40"><x-progress-bar :percent="62" label="Tiến độ lớp" tone="brand" /></div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'schedule')
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Buổi học</th><th class="px-4 py-3">Trạng thái</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($attendance as $a)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ $a['date'] }}</td>
                            <td class="px-4 py-3"><x-status-badge :tone="$a['tone']">{{ $a['status'] }}</x-status-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'results')
        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @foreach ($results as $r)
                <div class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-slate-700">{{ $r['title'] }}</p>
                        <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                    </div>
                    <x-status-badge :tone="$r['tone']">{{ $r['score'] }}</x-status-badge>
                </div>
            @endforeach
        </div>
    @elseif ($tab === 'review')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500 mb-3">Bạn đủ điều kiện đánh giá lớp này sau khi con tham gia ít nhất 2 buổi (9.2). Không đánh giá thay chuyên môn của con.</p>
            <a href="{{ route('reviews.form', ['type' => 'class', 'id' => 10]) }}" class="text-sm text-rose-600 font-medium">Viết đánh giá lớp ›</a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Buổi học tới</h3>
                <p class="text-sm text-slate-500">Hôm nay 19:00 — Cấu trúc dữ liệu nâng cao</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Điểm danh gần đây</h3>
                <p class="text-sm text-slate-500">6/8 buổi — 1 buổi vắng có phép</p>
            </div>
        </div>
    @endif
@endsection

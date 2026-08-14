{{--
  Route: parent.children.show | Frame: PAR-02
  Spec: 10.3 — lịch, điểm danh, tiến độ, kết quả, review lớp (phụ huynh
  đủ điều kiện sau 2 buổi/hoạt động của con — 9.2).
  Dữ liệu thật ($child, $classRoom, $tabsData, $results, $attendance, $nextSession,
  $overallPercent) do App\Http\Controllers\Parent\ChildController truyền vào qua
  App\Services\Parent\ChildService::showForParent().
--}}
@extends('layouts.parent')

@section('title', 'Chi tiết con')
@section('page-title', 'Chi tiết con')

@section('content')
    @php
        $tab = $tab ?? 'overview';
        $results = $results ?? [];
        $attendance = $attendance ?? [];
        $className = $classRoom->name ?? 'Chưa có lớp';
        $courseTitle = $classRoom->course->title ?? '';
        $nextSessionLabel = isset($nextSession) && $nextSession ? $nextSession->starts_at->format('d/m H:i') : 'Chưa có buổi học sắp tới';
    @endphp

    <a href="{{ route('parent.children.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Con của tôi</a>

    <div class="rounded-3xl bg-gradient-to-br from-violet-50 via-white to-rose-50 border border-slate-200 p-6 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 flex items-center justify-center font-medium text-slate-700 text-lg">{{ mb_substr($child->name ?? 'H', 0, 1) }}</div>
            <div>
                <h1 class="text-lg font-semibold text-slate-800">{{ $child->name ?? '' }}</h1>
                <p class="text-sm text-slate-500">Lớp {{ $className }}{{ $courseTitle ? ' · '.$courseTitle : '' }}</p>
            </div>
        </div>
        <div class="w-40"><x-progress-bar :percent="$overallPercent ?? 0" label="Tiến độ lớp" tone="brand" /></div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'schedule')
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="px-4 py-3">Buổi học</th><th class="px-4 py-3">Trạng thái</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($attendance as $a)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ $a['date'] }}</td>
                            <td class="px-4 py-3"><x-status-badge :tone="$a['tone']">{{ $a['status'] }}</x-status-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu điểm danh.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'results')
        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($results as $r)
                <div class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-slate-700">{{ $r['title'] }}</p>
                        <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                    </div>
                    <x-status-badge :tone="$r['tone']">{{ $r['score'] }}</x-status-badge>
                </div>
            @empty
                <div class="p-8"><x-empty-state title="Chưa có kết quả nào" /></div>
            @endforelse
        </div>
    @elseif ($tab === 'review')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            @if ($classRoom)
                <p class="text-sm text-slate-500 mb-3">Bạn đủ điều kiện đánh giá lớp này sau khi con tham gia ít nhất 2 buổi (9.2). Không đánh giá thay chuyên môn của con.</p>
                <a href="{{ route('reviews.form', ['type' => 'class', 'id' => $classRoom->id]) }}" class="text-sm text-rose-600 font-medium">Viết đánh giá lớp ›</a>
            @else
                <p class="text-sm text-slate-400">Con chưa có lớp đang học để đánh giá.</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Buổi học tới</h3>
                <p class="text-sm text-slate-500">{{ $nextSessionLabel }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Điểm danh gần đây</h3>
                <p class="text-sm text-slate-500">Xem chi tiết ở tab "Lịch & Điểm danh".</p>
            </div>
        </div>
    @endif
@endsection

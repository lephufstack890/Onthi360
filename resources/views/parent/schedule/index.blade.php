{{--
  Route: parent.schedule.index — "Lịch & Điểm danh" (source: ParentContent → "Lịch & Điểm danh").
  SỬA 12/10 — các thẻ buổi học sắp tới + bảng điểm danh gần đây cho con đang chọn (không còn
  trang "chọn con" trung gian). Chỉ con đã xác minh (10.3).
--}}
@extends('layouts.parent')

@section('title', 'Lịch & Điểm danh')
@section('page-title', 'Lịch & Điểm danh')

@section('content')
    @include('partials.parent-workspace-style')
    @php $single = count($viewChildren) === 1 ? $viewChildren[0] : null; @endphp

    <div class="pw">
        @include('parent._hero', [
            'title' => 'Lịch & điểm danh'.($single ? ' của '.$single['name'] : ' của các con'),
            'desc' => 'Các buổi học sắp tới và tình hình có mặt của con ở những buổi đã học.',
            'cta' => ['label' => 'Xem thông báo', 'href' => route('parent.notifications.index'), 'icon' => 'message-square-text'],
        ])

        @if (! $hasChildren)
            @include('parent._no-children')
        @else
            @if ($multi)
                <section class="pw-card pw-card--soft"><div class="pw-sum-top"><div><p class="pw-eyebrow">Theo dõi theo từng con</p><p class="pw-sub">Chọn một con để xem lịch riêng.</p></div>@include('parent._picker')</div></section>
            @endif

            <div class="pw-grid3">
                @include('parent._stat', ['label' => 'Điểm danh', 'value' => $attendancePercent !== null ? $attendancePercent.'%' : '—', 'note' => $pastSessions > 0 ? 'Có mặt '.$presentSessions.'/'.$pastSessions.' buổi đã học' : 'Chưa có buổi học nào', 'icon' => 'check-circle', 'tone' => 'emerald'])
                @include('parent._stat', ['label' => 'Buổi sắp tới', 'value' => count($upcoming), 'note' => $upcoming ? 'Buổi gần nhất: '.$upcoming[0]['when'].' · '.$upcoming[0]['start'] : 'Chưa có buổi học nào được lên lịch', 'icon' => 'calendar-days', 'tone' => 'blue'])
                @include('parent._stat', ['label' => 'Tiến độ lớp', 'value' => $progressPercent.'%', 'note' => $endedSessions.'/'.$totalSessions.' buổi đã diễn ra', 'icon' => 'notebook-pen', 'tone' => 'amber'])
            </div>

            <section class="pw-card" x-data="pwPager({{ count($upcoming) }}, 6)">
                @include('parent._ct', ['icon' => 'calendar-days', 'title' => 'Buổi học sắp tới'])
                @if ($upcoming)
                    <div class="pw-cards">
                        @foreach ($upcoming as $s)
                            <article class="pw-sess {{ $s['today'] ? 'today' : '' }}" x-show="show({{ $loop->index }})">
                                <b>{{ $s['weekday'] }} · {{ $s['today'] ? 'Hôm nay' : $s['when'] }}</b>
                                <p>{{ $s['time'] }}</p>
                                <p class="t">{{ $s['title'] }}</p>
                                <p>{{ $s['class'] }}{{ $multi && $s['child'] ? ' · '.$s['child'] : '' }}</p>
                                <span class="pw-tag {{ $s['today'] ? 'emerald' : 'blue' }}">● {{ $s['today'] ? 'Diễn ra hôm nay' : 'Sắp diễn ra' }}</span>
                            </article>
                        @endforeach
                    </div>
                    @include('parent._pager')
                @else
                    <p class="pw-empty">Chưa có buổi học nào được lên lịch.</p>
                @endif
            </section>

            <section class="pw-card" x-data="pwPager({{ count($attendanceList) }}, 10)">
                @include('parent._ct', ['icon' => 'check-circle', 'title' => 'Điểm danh gần đây'])
                <div class="pw-table-wrap">
                    <table class="pw-table">
                        <thead><tr><th>Ngày học</th>@if ($multi)<th>Học sinh</th>@endif<th>Nội dung</th><th>Trạng thái</th><th>Nhận xét</th></tr></thead>
                        <tbody>
                            @forelse ($attendanceList as $r)
                                <tr x-show="show({{ $loop->index }})">
                                    <td class="k">{{ $r['weekday'] }}<br><span style="font-weight:500;color:#64748b">{{ $r['date'] }}</span></td>
                                    @if ($multi)<td>{{ $r['child'] }}</td>@endif
                                    <td>{{ $r['topic'] ?: '—' }}</td>
                                    <td><span class="pw-tag {{ $r['tone'] }}" style="margin:0">● {{ $r['status'] }}</span></td>
                                    <td>{{ $r['note'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $multi ? 5 : 4 }}" class="pw-empty">Chưa có dữ liệu điểm danh.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('parent._pager')
            </section>
        @endif
    </div>
@endsection

{{--
  Route: parent.results.index — "Kết quả & Tiến độ" (source: ParentContent → "Kết quả & Tiến độ").
  SỬA 12/10 — 2 thẻ số lớn (Kết quả mới công bố + Tiến độ lớp) như bản mẫu, kèm bảng kết quả và
  tiến độ từng lớp. Chỉ con đã xác minh (10.3).
--}}
@extends('layouts.parent')

@section('title', 'Kết quả & Tiến độ')
@section('page-title', 'Kết quả & Tiến độ')

@section('content')
    @include('partials.parent-workspace-style')
    @php
        $single = count($viewChildren) === 1 ? $viewChildren[0] : null;
        $latest = $latestResult;
        $fmt = fn ($d) => rtrim(rtrim(number_format(abs($d), 2, '.', ''), '0'), '.') ?: '0';
    @endphp

    <div class="pw">
        @include('parent._hero', [
            'title' => 'Kết quả & tiến độ'.($single ? ' của '.$single['name'] : ' của các con'),
            'desc' => 'Điểm các bài đã nộp và mức độ hoàn thành lớp học, cập nhật theo hoạt động thực tế.',
            'cta' => ['label' => 'Xem bài được giao', 'href' => route('parent.assignments.index', ['child' => $selected]), 'icon' => 'clipboard-check'],
        ])

        @if (! $hasChildren)
            @include('parent._no-children')
        @else
            @if ($multi)
                <section class="pw-card pw-card--soft"><div class="pw-sum-top"><div><p class="pw-eyebrow">Theo dõi theo từng con</p><p class="pw-sub">Chọn một con để xem kết quả riêng.</p></div>@include('parent._picker')</div></section>
            @endif

            <section class="pw-cols2">
                <div class="pw-card">
                    @include('parent._ct', ['icon' => 'trophy', 'title' => 'Kết quả mới công bố'])
                    @if ($latest)
                        <p class="pw-big b">{{ $latest['score'] }}</p>
                        <p class="pw-big-t">{{ $latest['title'] }}{{ $multi ? ' · '.$latest['child'] : '' }}</p>
                        <p class="pw-note">{{ $latest['delta'] === null ? 'Đây là bài đầu tiên được chấm' : ($latest['delta'] >= 0 ? 'Tăng ' : 'Giảm ').$fmt($latest['delta']).' điểm so với lần trước' }} · {{ $latest['time'] }}</p>
                    @else
                        <p class="pw-empty">Chưa có bài nào được chấm.</p>
                    @endif
                </div>
                <div class="pw-card">
                    @include('parent._ct', ['icon' => 'medal', 'title' => 'Tiến độ lớp'])
                    <p class="pw-big g">{{ $progressPercent }}%</p>
                    <p class="pw-big-t">Đã diễn ra {{ $endedSessions }} / {{ $totalSessions }} buổi học</p>
                    <div class="pw-bar pw-bar--slim" style="margin-top:12px"><i class="g" style="width: {{ $progressPercent }}%"></i></div>
                    @if ($averageScore !== null)<p class="pw-note">Điểm trung bình các bài đã chấm: {{ $averageScore }}</p>@endif
                </div>
            </section>

            <section class="pw-card" x-data="pwPager({{ count($results) }}, 10)">
                @include('parent._ct', ['icon' => 'trophy', 'title' => 'Lịch sử kết quả'])
                <div class="pw-table-wrap">
                    <table class="pw-table">
                        <thead><tr>@if ($multi)<th>Học sinh</th>@endif<th>Bài / đề</th><th>Thời gian nộp</th><th class="r">Điểm</th></tr></thead>
                        <tbody>
                            @forelse ($results as $r)
                                <tr x-show="show({{ $loop->index }})">
                                    @if ($multi)<td class="k">{{ $r['child'] }}</td>@endif
                                    <td class="{{ $multi ? '' : 'k' }}">{{ $r['title'] }}</td>
                                    <td>{{ $r['date'] }}</td>
                                    <td class="r">@if ($r['pending'])<span class="pw-chip wait">Đang chấm</span>@else<span class="pw-sc">{{ $r['score'] }}</span>@if ($r['provisional'])<br><span style="font-size:10px;color:#94a3b8">tạm tính</span>@endif @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $multi ? 4 : 3 }}" class="pw-empty">Chưa có bài nào được nộp.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('parent._pager')
            </section>

            @if (count($classProgress) > 0)
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'book-open', 'title' => 'Tiến độ theo lớp học'])
                    <div class="pw-prog">
                        @foreach ($classProgress as $p)
                            <div>
                                <div class="pw-prog-h"><b>{{ $p['class'] }}{{ $multi ? ' · '.$p['child'] : '' }}</b><span>{{ $p['percent'] }}%</span></div>
                                <div class="pw-bar pw-bar--slim" style="margin-top:8px"><i class="{{ $p['percent'] >= 66 ? '' : ($p['percent'] >= 33 ? 'g' : 'a') }}" style="width: {{ $p['percent'] }}%"></i></div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
@endsection

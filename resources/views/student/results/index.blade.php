{{--
  Route: student.results.index — "Kết quả & lịch sử nộp" (source: StudentContent → "Kết quả & lịch sử nộp").
  SỬA 12/10 — MỤC MỚI. Điểm mới nhất, điểm cao nhất/trung bình, tiến độ lớp, bảng bài đã nộp (10 dòng/trang)
  có link xem chi tiết từng lượt làm; nút sang nhật ký nộp bài luyện tập.
--}}
@extends('layouts.student')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả')

@section('content')
    @include('partials.parent-workspace-style')
    @php $fmt = fn ($d) => rtrim(rtrim(number_format(abs($d), 2, '.', ''), '0'), '.') ?: '0'; @endphp

    <div class="pw">
        @include('student._hero', [
            'title' => 'Kết quả & lịch sử nộp',
            'desc' => 'Điểm các bài đã nộp và mức độ hoàn thành lớp học, cập nhật theo hoạt động thực tế của bạn.',
            'cta' => ['label' => 'Nhật ký nộp bài luyện tập', 'href' => route('student.practice.index', ['tab' => 'history']), 'icon' => 'history'],
        ])

        <div class="pw-grid4">
            @include('parent._stat', ['label' => 'Bài đã nộp', 'value' => $count, 'note' => $pendingCount.' bài đang chờ chấm', 'icon' => 'clipboard-check', 'tone' => 'blue'])
            @include('parent._stat', ['label' => 'Điểm trung bình', 'value' => $average ?? '—', 'note' => 'Các bài đã có điểm', 'icon' => 'bar-chart-3', 'tone' => 'emerald'])
            @include('parent._stat', ['label' => 'Điểm cao nhất', 'value' => $best['score'] ?? '—', 'note' => $best['title'] ?? 'Chưa có bài được chấm', 'icon' => 'medal', 'tone' => 'amber'])
            @include('parent._stat', ['label' => 'Tiến độ lớp', 'value' => $progressPercent.'%', 'note' => $ended.' / '.$sessions.' buổi học', 'icon' => 'trending-up', 'tone' => 'violet'])
        </div>

        <section class="pw-cols2">
            <div class="pw-card">
                @include('parent._ct', ['icon' => 'trophy', 'title' => 'Kết quả mới công bố'])
                @if ($latest)
                    <p class="pw-big b">{{ $latest['score'] }}</p>
                    <p class="pw-big-t">{{ $latest['title'] }}</p>
                    <p class="pw-note">{{ $latest['delta'] === null ? 'Đây là bài đầu tiên được chấm' : ($latest['delta'] >= 0 ? 'Tăng ' : 'Giảm ').$fmt($latest['delta']).' điểm so với lần trước' }} · {{ $latest['ago'] }}</p>
                @else
                    <p class="pw-empty">Chưa có bài nào được chấm.</p>
                @endif
            </div>
            <div class="pw-card">
                @include('parent._ct', ['icon' => 'book-open', 'title' => 'Tiến độ theo lớp học'])
                @forelse ($classProgress as $p)
                    <div class="pw-prog" style="{{ $loop->first ? '' : 'margin-top:14px' }}">
                        <div>
                            <div class="pw-prog-h"><b>{{ $p['class'] }}</b><span>{{ $p['percent'] }}%</span></div>
                            <div class="pw-bar pw-bar--slim" style="margin-top:8px"><i class="{{ $p['percent'] >= 66 ? '' : ($p['percent'] >= 33 ? 'g' : 'a') }}" style="width: {{ $p['percent'] }}%"></i></div>
                            <p class="pw-note">Đã diễn ra {{ $p['ended'] }} / {{ $p['total'] }} buổi{{ $p['course'] ? ' · '.$p['course'] : '' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="pw-empty">Bạn chưa tham gia lớp nào. <a href="{{ route('student.courses.index') }}" style="color:#2563eb;font-weight:700">Xem khóa học</a></p>
                @endforelse
            </div>
        </section>

        <section class="pw-card" x-data="pwPager({{ count($rows) }}, 10)">
            @include('parent._ct', ['icon' => 'history', 'title' => 'Lịch sử nộp bài'])
            <div class="pw-table-wrap">
                <table class="pw-table">
                    <thead><tr><th>Bài / đề</th><th>Thời gian nộp</th><th class="r">Điểm</th><th class="r"></th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr x-show="show({{ $loop->index }})">
                                <td class="k">{{ $r['title'] }}</td>
                                <td>{{ $r['date'] }}</td>
                                <td class="r">@if ($r['pending'])<span class="pw-chip wait">Đang chấm</span>@else<span class="pw-sc">{{ $r['score'] }}</span>@if ($r['provisional'])<br><span style="font-size:10px;color:#94a3b8">tạm tính</span>@endif @endif</td>
                                <td class="r"><a href="{{ $r['url'] }}" style="color:#2563eb;font-weight:700;font-size:12px;text-decoration:none;white-space:nowrap">Chi tiết ›</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pw-empty">Bạn chưa nộp bài nào. <a href="{{ route('student.assignments.index') }}" style="color:#2563eb;font-weight:700">Xem bài được giao</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('parent._pager')
        </section>
    </div>
@endsection

{{--
  Route: student.results.index — "Kết quả & Lịch sử".
  SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): StudentContent → "Kết quả & lịch sử nộp" — mỗi lần nộp là một dòng:
  chấm tròn trạng thái, tên bài, "Nộp lúc … · ngôn ngữ/loại", trạng thái chấm, điểm. Gộp bài lập trình luyện tập + đề đã nộp.
  Thêm so với source: 3 ô số tổng hợp, link xem chi tiết, phân trang 10 dòng/trang trên trình duyệt.
--}}
@extends('layouts.student')

@section('title', 'Kết quả & Lịch sử')
@section('page-title', 'Kết quả & Lịch sử')

@section('content')
    @php
        $rows = $rows ?? [];
        $items = array_map(fn ($r) => [
            'title' => $r['title'], 'when' => $r['when'], 'status' => $r['status'], 'tone' => $r['tone'],
            'score' => $r['score'], 'url' => $r['url'],
        ], $rows);
    @endphp

    <div class="sw" data-section="STUDENT-RESULTS">
        @include('student._hero')

        <section class="student-overview-card" x-data="stuResults(@js($items))">
            <div class="sw-split">
                <div>
                    <h2 class="sw-h2"><x-lucide name="trophy" />Kết quả &amp; lịch sử nộp</h2>
                    <p class="sw-sub">Mỗi dòng là một lần nộp bài; điểm hiện khi bài được chấm xong.</p>
                </div>
                <a class="student-overview-action" href="{{ route('student.practice.index', ['tab' => 'history']) }}">Nhật ký nộp bài luyện tập</a>
            </div>
            <dl class="sw-stat3">
                <div class="sw-t-blue"><dt><x-lucide name="clipboard-check" />Lần nộp</dt><dd>{{ $count ?? 0 }}</dd><p>Bài và đề đã nộp</p></div>
                <div class="sw-t-emerald"><dt><x-lucide name="check-circle-2" />Accepted</dt><dd>{{ $accepted ?? 0 }}</dd><p>{{ $average !== null ? 'Điểm trung bình '.$average : 'Chưa có bài được chấm' }}</p></div>
                <div class="sw-t-violet"><dt><x-lucide name="clock-3" />Đang chấm</dt><dd>{{ $waiting ?? 0 }}</dd><p>Chờ có kết quả</p></div>
            </dl>

            <div class="sw-rows" style="margin-top:12px">
                <template x-for="(r, idx) in items" :key="idx">
                    <div class="sw-row" x-show="show(idx)">
                        <span class="sw-dot" :class="r.tone"></span>
                        <div class="sw-row-main"><b x-text="r.title"></b><span x-text="r.when"></span></div>
                        <span class="sw-row-st" x-text="r.status"></span>
                        <span class="sw-row-sc" x-text="r.score"></span>
                        <a class="sw-detail-link" :href="r.url">Chi tiết ›</a>
                    </div>
                </template>
            </div>
            @if (count($rows) === 0)
                <p class="sw-empty">Bạn chưa nộp bài nào. <a href="{{ route('student.assignments.index') }}" style="color:#2563eb;font-weight:700">Xem bài &amp; đề được giao</a></p>
            @endif

            @include('student._pager')
        </section>
    </div>
    <script>
        function stuResults(items) { return window.swPager(items.length, 10, { items: items }); }
    </script>
@endsection

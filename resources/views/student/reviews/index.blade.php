{{--
  Route: student.reviews.index — "Đánh giá của tôi" (source: StudentContent → ReviewCenterPanel).
  SỬA 12/10 — MỤC MỚI. Lớp đủ điều kiện đánh giá (luật ReviewService::checkEligibility — đủ buổi tham gia),
  danh sách các đánh giá đã gửi (10/trang, trạng thái kiểm duyệt, sửa được trong 7 ngày) và nút sang form thật reviews.form.
--}}
@extends('layouts.student')

@section('title', 'Đánh giá của tôi')
@section('page-title', 'Đánh giá của tôi')

@section('content')
    @include('partials.parent-workspace-style')
    <style>
        .pw-rv { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 0; border-top: 1px solid #f1f5f9; }
        .pw-rv-b { min-width: 0; flex: 1 1 200px; }
        .pw-rv-b b { display: block; font-size: 12px; color: #1e293b; overflow-wrap: anywhere; }
        .pw-rv-b span { display: block; margin-top: 3px; font-size: 11px; color: #64748b; }
        .pw-rv-r { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .pw-rv .pw-btn { margin-top: 0; min-height: 34px; }
    </style>
    @php
        $toneMap = ['success' => 'ok', 'warning' => 'wait', 'danger' => 'rose', 'neutral' => 'off'];
        $first = $mine[0] ?? null;
    @endphp

    <div class="pw">
        @include('student._hero', [
            'title' => 'Đánh giá của tôi',
            'desc' => 'Chia sẻ trải nghiệm lớp học của bạn. Đánh giá được kiểm duyệt trước khi công bố và sửa được trong 7 ngày.',
            'cta' => ['label' => 'Xem trang đánh giá', 'href' => route('reviews.myReviews'), 'icon' => 'star'],
        ])

        <section class="pw-cols2 pw-cols-r">
            <div class="pw-card">
                @include('parent._ct', ['icon' => 'star', 'title' => 'Lớp bạn có thể đánh giá'])
                @forelse ($reviewable as $r)
                    <div style="{{ $loop->first ? '' : 'margin-top:12px' }}">
                        @if ($r['existing'])
                            <div class="pw-ok">
                                <b>Bạn đã đánh giá {{ $r['class'] }}</b>
                                <p><span class="pw-stars">{{ str_repeat('★', $r['existing']['rating']) }}{{ str_repeat('☆', max(0, 5 - $r['existing']['rating'])) }}</span>
                                    · {{ $r['existing']['status'] === 'published' ? 'Đã công bố' : 'Đang chờ kiểm duyệt' }}
                                    @if ($r['existing']['editable']) · còn sửa được đến {{ $r['existing']['editableUntil'] }}@endif</p>
                                @if ($r['existing']['comment'])<p style="margin-top:6px">“{{ \Illuminate\Support\Str::limit($r['existing']['comment'], 160) }}”</p>@endif
                                @if ($r['existing']['editable'])<a class="pw-btn pw-btn--ghost" style="margin-top:10px" href="{{ route('reviews.form', ['type' => 'class', 'id' => $r['classId']]) }}">Sửa đánh giá</a>@endif
                            </div>
                        @elseif ($r['allowed'])
                            <div class="pw-ok">
                                <b>Bạn đủ điều kiện đánh giá {{ $r['class'] }}</b>
                                <p>Bạn đã tham gia đủ số buổi theo quy định{{ $r['course'] ? ' của '.$r['course'] : '' }}. Hãy chia sẻ trải nghiệm thật của bạn về lớp học.</p>
                                <a class="pw-btn" style="margin-top:10px" href="{{ route('reviews.form', ['type' => 'class', 'id' => $r['classId']]) }}">Viết đánh giá</a>
                            </div>
                        @else
                            <div class="pw-warn">
                                <b>Chưa thể đánh giá {{ $r['class'] }}</b>
                                <p>{{ $r['message'] ?: 'Bạn cần tham gia đủ số buổi học theo quy định trước khi đánh giá.' }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="pw-empty">Bạn chưa có lớp đang học để đánh giá. <a href="{{ route('student.courses.index') }}" style="color:#2563eb;font-weight:700">Xem khóa học</a></p>
                @endforelse
            </div>

            <aside class="pw-card">
                @include('parent._ct', ['icon' => 'shield-check', 'title' => 'Nguyên tắc hiển thị'])
                <ul class="pw-rules">
                    <li>✓ Đánh giá được kiểm duyệt trước khi công bố.</li>
                    <li>✓ Chỉ học sinh đã tham gia đủ số buổi mới được đánh giá lớp.</li>
                    <li>✓ Bạn có thể sửa đánh giá trong 7 ngày sau khi gửi.</li>
                </ul>
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9">
                    <p class="pw-stat-lb">Đánh giá gần nhất</p>
                    <p class="pw-li-t" style="margin-top:8px">{{ $first ? $first['target'] : 'Chưa có đánh giá nào được gửi' }}</p>
                    @if ($first)<p class="pw-li-s">{{ str_repeat('★', $first['rating']) }} · {{ $first['status'] }} · {{ $first['time'] }}</p>@endif
                </div>
            </aside>
        </section>

        <section class="pw-card" x-data="pwPager({{ count($mine) }}, 10)">
            @include('parent._ct', ['icon' => 'message-square-text', 'title' => 'Các đánh giá tôi đã gửi'])
            @forelse ($mine as $r)
                <div class="pw-rv" x-show="show({{ $loop->index }})">
                    <div class="pw-rv-b">
                        <b>{{ $r['target'] }}</b>
                        <span><span class="pw-stars" style="display:inline">{{ str_repeat('★', $r['rating']) }}{{ str_repeat('☆', max(0, 5 - $r['rating'])) }}</span> · {{ $r['time'] }}</span>
                    </div>
                    <div class="pw-rv-r">
                        <span class="pw-chip {{ $toneMap[$r['tone']] ?? 'off' }}">{{ $r['status'] }}</span>
                        @if ($r['isEditable'])<a class="pw-btn pw-btn--ghost" href="{{ $r['url'] }}">Sửa</a>@endif
                    </div>
                </div>
            @empty
                <p class="pw-empty">Bạn chưa gửi đánh giá nào.</p>
            @endforelse
            @include('parent._pager')
        </section>
    </div>
@endsection

{{--
  Route: parent.reviews.index — "Đánh giá lớp" (source: ParentContent → "Đánh giá lớp").
  SỬA 12/10 — MỤC MỚI. Hiện điều kiện đánh giá lớp của từng con (luật cũ ReviewService::
  checkEligibility: đủ buổi tham gia — 9.2), đánh giá đã gửi (sửa được trong 7 ngày) và nút
  sang form thật reviews.form. Nguyên tắc hiển thị đúng như bản mẫu.
--}}
@extends('layouts.parent')

@section('title', 'Đánh giá lớp')
@section('page-title', 'Đánh giá lớp')

@section('content')
    @include('partials.parent-workspace-style')

    <div class="pw">
        @include('parent._hero', [
            'title' => 'Đánh giá lớp học',
            'desc' => 'Chia sẻ điều bạn quan sát được về thông tin, tổ chức lớp và tiến độ của con.',
            'cta' => ['label' => 'Đánh giá của tôi', 'href' => route('reviews.myReviews'), 'icon' => 'star'],
        ])

        @if (! $hasChildren)
            @include('parent._no-children')
        @else
            @if ($multi)
                <section class="pw-card pw-card--soft"><div class="pw-sum-top"><div><p class="pw-eyebrow">Theo dõi theo từng con</p><p class="pw-sub">Chọn một con để xem lớp có thể đánh giá.</p></div>@include('parent._picker')</div></section>
            @endif

            <section class="pw-cols2 pw-cols-r">
                <div class="pw-card">
                    @include('parent._ct', ['icon' => 'star', 'title' => 'Chia sẻ trải nghiệm lớp học'])
                    @forelse ($reviewable as $r)
                        <div style="{{ $loop->first ? '' : 'margin-top:12px' }}">
                            @if ($r['existing'])
                                <div class="pw-ok">
                                    <b>Bạn đã đánh giá {{ $r['class'] }}{{ $multi ? ' ('.$r['child'].')' : '' }}</b>
                                    <p><span class="pw-stars">{{ str_repeat('★', $r['existing']['rating']) }}{{ str_repeat('☆', max(0, 5 - $r['existing']['rating'])) }}</span>
                                        · {{ $r['existing']['status'] === 'published' ? 'Đã công bố' : 'Đang chờ kiểm duyệt' }}
                                        @if ($r['existing']['editable']) · còn sửa được đến {{ $r['existing']['editableUntil'] }}@endif</p>
                                    @if ($r['existing']['comment'])<p style="margin-top:6px">“{{ \Illuminate\Support\Str::limit($r['existing']['comment'], 160) }}”</p>@endif
                                    @if ($r['existing']['editable'])<a class="pw-btn pw-btn--ghost" style="margin-top:10px" href="{{ route('reviews.form', ['type' => 'class', 'id' => $r['classId']]) }}">Sửa đánh giá</a>@endif
                                </div>
                            @elseif ($r['allowed'])
                                <div class="pw-ok">
                                    <b>Bạn đủ điều kiện đánh giá {{ $r['class'] }}</b>
                                    <p>{{ $r['child'] }} đã tham gia đủ số buổi theo quy định. Đánh giá tập trung vào thông tin, tổ chức và tiến độ quan sát được.</p>
                                    <a class="pw-btn" style="margin-top:10px" href="{{ route('reviews.form', ['type' => 'class', 'id' => $r['classId']]) }}">Viết đánh giá xác thực</a>
                                </div>
                            @else
                                <div class="pw-warn">
                                    <b>Chưa thể đánh giá {{ $r['class'] }}{{ $multi ? ' ('.$r['child'].')' : '' }}</b>
                                    <p>{{ $r['message'] ?: 'Con cần tham gia đủ số buổi học theo quy định trước khi phụ huynh đánh giá.' }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="pw-empty">Con chưa có lớp đang học để đánh giá.</p>
                    @endforelse
                </div>

                <aside class="pw-card">
                    @include('parent._ct', ['icon' => 'shield-check', 'title' => 'Nguyên tắc hiển thị'])
                    <ul class="pw-rules">
                        <li>✓ Đánh giá sẽ được kiểm duyệt trước khi công bố.</li>
                        <li>✓ Công khai dưới nhãn “Phụ huynh đã xác thực”, không hiển thị thông tin của trẻ em.</li>
                        <li>✓ Bạn có thể sửa đánh giá trong 7 ngày sau khi gửi.</li>
                    </ul>
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9">
                        <p class="pw-stat-lb">Đánh giá gần nhất</p>
                        @php $mine = collect($reviewable)->first(fn ($x) => $x['existing']); @endphp
                        <p class="pw-li-t" style="margin-top:8px">{{ $mine ? $mine['class'] : 'Chưa có đánh giá nào được gửi' }}</p>
                        <a class="pw-ct" style="margin:6px 0 0;display:inline-flex;font-size:12px;font-weight:700;color:#2563eb;text-decoration:none" href="{{ route('reviews.myReviews') }}">Xem các đánh giá tôi đã viết ›</a>
                    </div>
                </aside>
            </section>
        @endif
    </div>
@endsection

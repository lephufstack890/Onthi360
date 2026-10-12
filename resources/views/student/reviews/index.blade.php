{{--
  Route: student.reviews.index — "Đánh giá của tôi".
  SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): ReviewCenterPanel (CompletenessPanels.jsx) — tiêu đề "Đánh giá trải nghiệm",
  3 tab "Đánh giá của tôi / Đủ điều kiện / Chưa đủ điều kiện". Dữ liệu thật: Student\StudentWorkspaceService::reviews()
  (luật ReviewService::checkEligibility); nút "Viết/Sửa đánh giá" mở form thật reviews.form. "Đánh giá của tôi" phân trang 10/trang.
--}}
@extends('layouts.student')

@section('title', 'Đánh giá của tôi')
@section('page-title', 'Đánh giá của tôi')

@section('content')
    @php
        $reviewable = $reviewable ?? [];
        $mine = $mine ?? [];
        $eligible = array_values(array_filter($reviewable, fn ($r) => $r['allowed'] && ! $r['existing']));
        $blocked = array_values(array_filter($reviewable, fn ($r) => ! $r['allowed'] && ! $r['existing']));
        $stars = fn (int $n) => str_repeat('★', $n).str_repeat('☆', max(0, 5 - $n));
    @endphp

    <div class="sw" data-section="STUDENT-REVIEWS">
        @include('student._hero')

        <section class="student-overview-card" x-data="stuReviews({{ count($mine) }})">
            <h2 class="sw-h2"><x-lucide name="star" />Đánh giá trải nghiệm</h2>
            <p class="sw-sub">Chỉ hiển thị sau khi tài khoản đạt điều kiện trải nghiệm thực. Đánh giá được kiểm duyệt trước khi công bố và sửa được trong 7 ngày.</p>

            <div class="sw-tabs" role="tablist" style="margin-top:12px">
                <button type="button" class="sw-tab" role="tab" :class="{ on: tab === 'mine' }" @click="tab = 'mine'">Đánh giá của tôi ({{ count($mine) }})</button>
                <button type="button" class="sw-tab" role="tab" :class="{ on: tab === 'eligible' }" @click="tab = 'eligible'">Đủ điều kiện ({{ count($eligible) }})</button>
                <button type="button" class="sw-tab" role="tab" :class="{ on: tab === 'blocked' }" @click="tab = 'blocked'">Chưa đủ điều kiện ({{ count($blocked) }})</button>
            </div>

            {{-- Tab 1: đánh giá đã gửi --}}
            <div x-show="tab === 'mine'">
                <div class="sw-rows" style="margin-top:16px">
                    @forelse ($mine as $r)
                        <div class="sw-rv" x-show="show({{ $loop->index }})">
                            <span class="star"><x-lucide name="star" /></span>
                            <div class="sw-rv-b">
                                <b>{{ $r['target'] }}</b>
                                <span>{{ $stars((int) $r['rating']) }} · {{ $r['time'] }}</span>
                            </div>
                            <span class="sw-rv-st">{{ $r['status'] }}</span>
                            @if ($r['isEditable'])
                                <a class="sw-btn-o" href="{{ $r['url'] }}">Xem / sửa</a>
                            @else
                                <a class="sw-btn-o" href="{{ route('reviews.myReviews') }}">Xem</a>
                            @endif
                        </div>
                    @empty
                        <p class="sw-empty">Bạn chưa gửi đánh giá nào.</p>
                    @endforelse
                </div>
                @include('student._pager')
            </div>

            {{-- Tab 2: đủ điều kiện --}}
            <div x-show="tab === 'eligible'" x-cloak>
                @forelse ($eligible as $r)
                    <div class="sw-box ok">
                        <b>Bạn đủ điều kiện đánh giá “{{ $r['class'] }}”</b>
                        <p>Bạn đã tham gia đủ số buổi theo quy định{{ $r['course'] ? ' của '.$r['course'] : '' }}. Hãy chia sẻ trải nghiệm thật của bạn về lớp học.</p>
                        <a class="sw-btn-g" href="{{ route('reviews.form', ['type' => 'class', 'id' => $r['classId']]) }}">Viết đánh giá</a>
                    </div>
                @empty
                    <p class="sw-empty">Hiện chưa có lớp nào đủ điều kiện để bạn đánh giá.</p>
                @endforelse
                @foreach ($reviewable as $r)
                    @if ($r['existing'])
                        <div class="sw-box ok">
                            <b>Bạn đã đánh giá “{{ $r['class'] }}”</b>
                            <p>{{ $stars($r['existing']['rating']) }} · {{ $r['existing']['status'] === 'published' ? 'Đã công bố' : 'Đang chờ kiểm duyệt' }}@if ($r['existing']['editable']) · còn sửa được đến {{ $r['existing']['editableUntil'] }}@endif</p>
                            @if ($r['existing']['editable'])<a class="sw-btn-g" href="{{ route('reviews.form', ['type' => 'class', 'id' => $r['classId']]) }}">Sửa đánh giá</a>@endif
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Tab 3: chưa đủ điều kiện --}}
            <div x-show="tab === 'blocked'" x-cloak>
                @forelse ($blocked as $r)
                    <div class="sw-box warn">
                        <b>Chưa đủ điều kiện đánh giá “{{ $r['class'] }}”</b>
                        <p>{{ $r['message'] ?: 'Bạn cần tham gia đủ số buổi học theo quy định trước khi đánh giá.' }}</p>
                    </div>
                @empty
                    <p class="sw-empty">{{ count($reviewable) === 0 ? 'Bạn chưa có lớp đang học để đánh giá.' : 'Không có lớp nào đang bị chặn đánh giá.' }}</p>
                @endforelse
            </div>
        </section>
    </div>
    <script>
        function stuReviews(total) { return window.swPager(total, 10, { tab: 'mine' }); }
    </script>
@endsection

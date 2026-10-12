{{--
  Route: parent.children.index — mục "Con của tôi" (source: ParentContent → "Con của tôi").
  SỬA 12/10 — thẻ hồ sơ từng con (3 số liệu) + "Tiến độ theo lớp" + "Hoạt động gần đây" +
  khối quản lý liên kết (form "Gửi yêu cầu" giữ nguyên route/logic cũ: parent.children.linkRequest,
  chờ admin xác minh — 10.3, không tìm kiếm học sinh khác 3.3).
--}}
@extends('layouts.parent')

@section('title', 'Con của tôi')
@section('page-title', 'Con của tôi')

@section('content')
    @include('partials.parent-workspace-style')
    @php
        $childrenStatusMessage = match (session('status')) {
            'link-requested' => 'Đã gửi yêu cầu liên kết — chờ admin xác minh trước khi bạn xem được dữ liệu của con.',
            default => null,
        };
    @endphp

    @if ($childrenStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $childrenStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="pw">
        @include('parent._hero', [
            'title' => 'Con của tôi',
            'desc' => 'Bạn chỉ xem được dữ liệu của học sinh đã xác minh liên kết với tài khoản này.',
            'cta' => ['label' => 'Xem kết quả', 'href' => route('parent.results.index', ['child' => $selected]), 'icon' => 'trophy'],
        ])

        @if ($multi)
            <section class="pw-card pw-card--soft"><div class="pw-sum-top"><div><p class="pw-eyebrow">Theo dõi theo từng con</p><p class="pw-sub">Chọn một con để xem chi tiết hoặc xem tất cả.</p></div>@include('parent._picker')</div></section>
        @endif

        @forelse ($viewChildren as $c)
            @php
                $cp = collect($classProgress)->firstWhere('childId', $c['id']);
                $last = collect($results)->firstWhere('childId', $c['id']);
                $acts = collect($activity)->where('child', $c['name'])->take(4);
            @endphp
            <section class="pw-card pw-kid">
                <div class="pw-kid-top">
                    <span class="pw-av pw-av--lg">@if ($c['avatar'])<img src="{{ $c['avatar'] }}" alt="{{ $c['name'] }}">@else{{ $c['initial'] }}@endif</span>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2>{{ $c['name'] }}</h2><span class="pw-chip ok">● Đã liên kết &amp; xác minh</span></div>
                        <p class="pw-sub">{{ $c['class'] }}{{ $c['course'] ? ' · '.$c['course'] : '' }}</p>
                        <p class="pw-note" style="margin-top:8px">Bạn chỉ đang xem dữ liệu của học sinh đã xác minh liên kết với tài khoản này.</p>
                    </div>
                    <a class="pw-btn pw-btn--ghost" href="#lien-ket">Quản lý liên kết</a>
                </div>
                <div class="pw-kid-stats">
                    <div><small>Tiến độ lớp</small><strong style="color:#2563eb">{{ $cp['percent'] ?? 0 }}%</strong><p>{{ $cp ? $cp['ended'].' / '.$cp['total'].' buổi đã diễn ra' : 'Chưa có lớp' }}</p></div>
                    <div><small>Điểm gần nhất</small><strong style="color:#059669">{{ $last && ! $last['pending'] ? $last['score'] : '—' }}</strong><p>{{ $last ? $last['title'] : 'Chưa có bài nào được chấm' }}</p></div>
                    <div><small>Điểm danh</small><strong style="color:#1e293b">{{ $cp ? $cp['present'].'/'.$cp['past'] : '—' }}</strong><p>{{ $cp && $cp['past'] > 0 ? 'Có mặt '.$cp['present'].' trong '.$cp['past'].' buổi đã học' : 'Chưa có dữ liệu điểm danh' }}</p></div>
                </div>
            </section>
        @empty
            @include('parent._no-children')
        @endforelse

        @if ($hasChildren)
            <div class="pw-cols">
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'book-open', 'title' => 'Tiến độ theo lớp học', 'action' => 'Xem kết quả', 'href' => route('parent.results.index', ['child' => $selected])])
                    <div class="pw-prog">
                        @forelse ($classProgress as $p)
                            <div>
                                <div class="pw-prog-h"><b>{{ $p['class'] }}{{ $multi && $selected === 'all' ? ' · '.$p['child'] : '' }}</b><span>{{ $p['percent'] }}%</span></div>
                                <div class="pw-bar pw-bar--slim" style="margin-top:8px"><i class="{{ $p['percent'] >= 66 ? '' : ($p['percent'] >= 33 ? 'g' : 'a') }}" style="width: {{ $p['percent'] }}%"></i></div>
                            </div>
                        @empty
                            <p class="pw-empty">Con chưa tham gia lớp nào.</p>
                        @endforelse
                    </div>
                </section>
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'clock', 'title' => 'Hoạt động gần đây'])
                    <ul class="pw-list align-start">
                        @forelse ($activity as $a)
                            <li><span class="pw-dot {{ $a['tone'] }}"></span><div style="min-width:0"><p class="pw-li-top {{ $a['tone'] }}">{{ $a['status'] }}{{ $multi && $selected === 'all' ? ' · '.$a['child'] : '' }}</p><p class="pw-li-t" style="margin-top:2px">{{ $a['title'] }}</p><p class="pw-li-s">{{ $a['time'] }}</p></div></li>
                        @empty
                            <li class="pw-empty" style="display:block;width:100%">Chưa có hoạt động nào.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        @endif

        {{-- Quản lý liên kết (ParentLinkPanel) --}}
        <div class="pw-cols2 pw-cols-r" id="lien-ket">
            <section class="pw-card">
                @include('parent._ct', ['icon' => 'users', 'title' => 'Học sinh đã liên kết'])
                @forelse ($links as $l)
                    <div class="pw-link">
                        <span class="pw-av">{{ mb_strtoupper(mb_substr($l['name'], 0, 1)) }}</span>
                        <div class="nm"><b>{{ $l['name'] }}</b><span>{{ \App\Services\Parent\ParentWorkspaceService::classLabel($l['class'] === 'Chưa có lớp' ? null : $l['class']) }}</span></div>
                        <span class="pw-chip {{ $l['tone'] === 'success' ? 'ok' : ($l['tone'] === 'warning' ? 'wait' : 'off') }}">{{ $l['status'] }}</span>
                    </div>
                @empty
                    <p class="pw-empty">Chưa có yêu cầu liên kết nào.</p>
                @endforelse
            </section>
            <section class="pw-card">
                @include('parent._ct', ['icon' => 'shield-check', 'title' => 'Yêu cầu liên kết mới'])
                <form method="POST" action="{{ route('parent.children.linkRequest') }}" class="pw-form">
                    @csrf
                    <input type="email" name="student_email" required maxlength="255" value="{{ old('student_email') }}" placeholder="Email của con" aria-label="Email của con">
                    <button type="submit" class="pw-btn" style="margin-top:0">Gửi yêu cầu</button>
                </form>
                <p class="pw-note">Yêu cầu cần được quản trị viên xác minh trước khi bạn xem được dữ liệu của con.</p>
            </section>
        </div>
    </div>
@endsection

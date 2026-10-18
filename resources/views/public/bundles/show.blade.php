@extends('layouts.guest')

@section('title', $product->title)
@section('meta-description', 'Bộ đề '.$product->title.' trên Ôn Thi 360 — danh sách đề luyện tập, tiến độ làm bài.')

{{-- SỬA 18/10 — MODULE BỘ ĐỀ: trang bộ đề cho người xem (route bundles.show). Dữ liệu: ExamBundleService::viewerData().
     · Ai cũng xem được danh sách đề (biết bộ có gì trước khi mua).
     · Đề công khai: bấm vào là sang trang chi tiết đề (làm miễn phí như ở Luyện tập).
     · Đề "Chỉ trong bộ đề": khoá + nút Mua khi chưa có quyền bộ đề.
     · Đã đăng nhập: trạng thái từng đề (Chưa làm / Đang làm / Đã làm + điểm cao nhất) và tiến độ cả bộ.
     CSS tự chứa (tiền tố ebp-) vì máy chủ không build lại Tailwind. --}}
@section('content')
@php
    $total = count($items);
    $pct = $total > 0 ? (int) round($doneCount / $total * 100) : 0;
    $cover = \App\Support\ProductCover::url($product->cover_image_path);
    $user = auth()->user();
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp
<style>
    .ebp { max-width: 1180px; margin: 0 auto; padding: 16px 12px 40px; }
    .ebp-back { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid #dbeafe; border-radius: 12px; background: #fff; font-size: 12px; font-weight: 700; color: #475569; text-decoration: none; }
    .ebp-back:hover { color: #1d4ed8; border-color: #bfdbfe; }
    .ebp-back svg { width: 14px; height: 14px; }
    .ebp-hero { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 14px; padding: 20px; border: 1px solid #dbeafe; border-radius: 24px; background: #fff; box-shadow: 0 2px 10px rgba(0, 100, 220, .04); }
    .ebp-cover { width: 132px; flex-shrink: 0; aspect-ratio: 3/4; overflow: hidden; border: 1px solid #dbeafe; border-radius: 16px; background: linear-gradient(135deg, #e0f2fe, #ede9fe); display: grid; place-items: center; color: #1d4ed8; }
    .ebp-cover img { width: 100%; height: 100%; object-fit: cover; }
    .ebp-cover svg { width: 44px; height: 44px; }
    .ebp-info { flex: 1; min-width: 240px; }
    .ebp-kicker { margin: 0; font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
    .ebp-title { margin: 4px 0 0; font-size: 24px; font-weight: 800; line-height: 1.3; color: #0f3a7a; }
    .ebp-stats { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .ebp-stat { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #e2e8f0; border-radius: 999px; background: #f8fbfe; font-size: 12.5px; font-weight: 700; color: #36506a; }
    .ebp-stat svg { width: 14px; height: 14px; color: #2d7fa3; }
    .ebp-progress { margin-top: 14px; max-width: 460px; }
    .ebp-progress p { display: flex; justify-content: space-between; margin: 0 0 6px; font-size: 12px; font-weight: 700; color: #36506a; }
    .ebp-bar { height: 8px; border-radius: 999px; background: #e2edf5; overflow: hidden; }
    .ebp-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #2f8a6b, #3fb38a); }
    .ebp-cta { display: flex; flex-direction: column; gap: 8px; width: 260px; max-width: 100%; }
    .ebp-price { margin: 0; font-size: 26px; font-weight: 900; line-height: 1; color: #0b3c78; }
    .ebp-btn { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 44px; padding: 0 16px; border-radius: 14px; font-size: 13px; font-weight: 800; text-decoration: none; }
    .ebp-btn svg { width: 16px; height: 16px; }
    .ebp-btn--buy { background: #126f91; color: #fff; }
    .ebp-btn--buy:hover { background: #0f5f7a; }
    .ebp-btn--ghost { border: 1px solid #dbeafe; background: #fff; color: #1d4ed8; }
    .ebp-owned { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 12px; border: 1px solid #a7f3d0; border-radius: 14px; background: #ecfdf5; font-size: 13px; font-weight: 800; color: #047857; }
    .ebp-owned svg { width: 16px; height: 16px; }
    .ebp-note { margin: 0; font-size: 11.5px; line-height: 1.6; color: #64748b; }
    .ebp-list { margin: 16px 0 0; padding: 0; list-style: none; border: 1px solid #dbeafe; border-radius: 20px; overflow: hidden; background: #fff; }
    .ebp-row { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-bottom: 1px solid #eef3f8; }
    .ebp-row:last-child { border-bottom: 0; }
    .ebp-row:nth-child(even) { background: #fbfdff; }
    .ebp-no { flex-shrink: 0; width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; background: #eef4fb; font-size: 13px; font-weight: 800; color: #3b5b7d; }
    .ebp-main { flex: 1; min-width: 0; }
    .ebp-name { margin: 0; font-size: 14.5px; font-weight: 800; color: #123b68; overflow-wrap: anywhere; }
    .ebp-name a { color: inherit; text-decoration: none; }
    .ebp-name a:hover { color: #126f91; text-decoration: underline; }
    .ebp-meta { margin: 3px 0 0; font-size: 12px; color: #6b8295; }
    .ebp-badge { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 6px; background: #fef3c7; color: #92400e; font-size: 10.5px; font-weight: 800; vertical-align: middle; }
    .ebp-state { flex-shrink: 0; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
    .ebp-state--todo { background: #f1f5f9; color: #64748b; }
    .ebp-state--doing { background: #fff7e3; color: #8e6b2e; }
    .ebp-state--done { background: #eff9f5; color: #2f8a6b; }
    .ebp-go { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 0 14px; border-radius: 10px; background: #2f8a6b; color: #fff; font-size: 12.5px; font-weight: 800; text-decoration: none; }
    .ebp-go:hover { background: #28795e; }
    .ebp-go--lock { background: #f1f5f9; color: #64748b; }
    .ebp-go--lock:hover { background: #e2e8f0; }
    .ebp-go svg { width: 14px; height: 14px; }
    .ebp-empty { margin-top: 16px; padding: 28px; border: 1px dashed #c9dfe8; border-radius: 20px; background: #fff; text-align: center; font-size: 13.5px; color: #64748b; }
    @media (max-width: 720px) {
        .ebp-row { flex-wrap: wrap; }
        .ebp-main { flex-basis: calc(100% - 50px); }
        .ebp-state { margin-left: 48px; }
        .ebp-go { margin-left: auto; }
        .ebp-cta { width: 100%; }
    }
</style>

<div class="ebp">
    <a href="{{ route('materials.show', $product->id) }}" class="ebp-back"><x-lucide name="arrow-left" /> Quay lại trang tài liệu</a>

    <section class="ebp-hero">
        <div class="ebp-cover">
            @if ($cover)
                <img src="{{ $cover }}" alt="Bìa {{ $product->title }}" loading="lazy">
            @else
                <x-lucide name="clipboard-list" />
            @endif
        </div>

        <div class="ebp-info">
            <p class="ebp-kicker">Bộ đề</p>
            <h1 class="ebp-title">{{ $product->title }}</h1>
            <div class="ebp-stats">
                <span class="ebp-stat"><x-lucide name="file-text" /> {{ $total }} đề</span>
                <span class="ebp-stat"><x-lucide name="clipboard-list" /> {{ $totalQuestions }} câu</span>
                @if ($bundleOnlyCount > 0)
                    <span class="ebp-stat"><x-lucide name="lock" /> {{ $bundleOnlyCount }} đề chỉ có trong bộ đề</span>
                @endif
            </div>
            @if ($user && $total > 0)
                <div class="ebp-progress">
                    <p><span>Tiến độ của bạn</span><span>Đã làm {{ $doneCount }}/{{ $total }} đề</span></p>
                    <div class="ebp-bar" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $pct }}%"></span></div>
                </div>
            @endif
        </div>

        <div class="ebp-cta">
            @if ($hasAccess)
                <p class="ebp-owned"><x-lucide name="check-circle-2" /> Bạn đã sở hữu bộ đề này</p>
                @php
                    $readRoute = match (true) {
                        (bool) $user?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin.products.read',
                        (bool) $user?->hasRole(\App\Models\Role::TEACHER) => 'teacher.products.read',
                        default => 'student.products.read',
                    };
                @endphp
                <a href="{{ route($readRoute, $product->id) }}" class="ebp-btn ebp-btn--buy"><x-lucide name="book-open" /> Đọc &amp; làm theo đề</a>
                <p class="ebp-note">Làm được mọi đề trong bộ, kể cả đề chỉ có trong bộ đề.</p>
            @else
                <p class="ebp-price">{{ $product->price > 0 ? number_format($product->price).'đ' : 'Miễn phí' }}</p>
                @if ($user)
                    <a href="{{ route('access.checkout', $product->id) }}" class="ebp-btn ebp-btn--buy"><x-lucide name="wallet-cards" /> Mua bộ đề</a>
                @else
                    <a href="{{ route('login') }}" class="ebp-btn ebp-btn--buy"><x-lucide name="log-in" /> Đăng nhập để mua</a>
                @endif
                <a href="{{ route('access.activate') }}" class="ebp-btn ebp-btn--ghost"><x-lucide name="key-round" /> Đã có mã? Kích hoạt</a>
                <p class="ebp-note">Đề công khai trong bộ vẫn làm miễn phí ở trang Luyện tập. Mua bộ đề để mở {{ $bundleOnlyCount > 0 ? 'thêm '.$bundleOnlyCount.' đề chỉ có trong bộ và ' : '' }}theo dõi tiến độ cả bộ.</p>
            @endif
        </div>
    </section>

    @if ($total === 0)
        <div class="ebp-empty">Bộ đề đang được cập nhật — chưa có đề nào.</div>
    @else
        <ol class="ebp-list">
            @foreach ($items as $i => $it)
                <li class="ebp-row">
                    <span class="ebp-no">{{ $i + 1 }}</span>
                    <div class="ebp-main">
                        <p class="ebp-name">
                            @if ($it['locked'])
                                {{ $it['title'] }}
                            @else
                                <a href="{{ $it['href'] }}">{{ $it['title'] }}</a>
                            @endif
                            @if ($it['bundleOnly'])<span class="ebp-badge">Chỉ trong bộ đề</span>@endif
                        </p>
                        <p class="ebp-meta">{{ collect([$it['category'], $it['level'], $it['province'], $it['year']])->filter()->implode(' · ') }}{{ $it['category'] || $it['province'] || $it['year'] ? ' · ' : '' }}{{ $it['questions'] }} câu{{ $it['duration'] ? ' · '.$it['duration'].' phút' : '' }}</p>
                    </div>
                    @if ($user)
                        @if ($it['status'] === 'done')
                            <span class="ebp-state ebp-state--done">Đã làm{{ $it['bestScore'] !== null ? ' · '.$fmt($it['bestScore']).'/'.$fmt($it['points']) : '' }}</span>
                        @elseif ($it['status'] === 'doing')
                            <span class="ebp-state ebp-state--doing">Đang làm</span>
                        @else
                            <span class="ebp-state ebp-state--todo">Chưa làm</span>
                        @endif
                    @endif
                    @if ($it['locked'])
                        <a href="{{ $user ? route('access.checkout', $product->id) : route('login') }}" class="ebp-go ebp-go--lock" title="Mua bộ đề để làm đề này"><x-lucide name="lock" /> Mua để làm</a>
                    @else
                        <a href="{{ $it['href'] }}" class="ebp-go"><x-lucide name="file-text" /> {{ ($user && $it['status'] !== 'todo') ? 'Làm lại' : 'Làm đề' }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
@endsection

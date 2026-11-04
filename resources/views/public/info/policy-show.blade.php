@extends('layouts.guest')

@section('title', $title ?? 'Chính sách')

@section('content')
    @php
        $title = $title ?? '';
        $desc = $desc ?? '';
        $sections = $sections ?? [];
        $updated = $updated ?? null;
        $slug = $slug ?? '';
        $polIcon = ['dieu-khoan' => 'file-text', 'bao-mat' => 'shield-check', 'hoan-tien' => 'calendar-days'][$slug] ?? 'file-text';
    @endphp

    {{-- SỬA 3/11 (khách: "làm lại UI trang Điều khoản sử dụng đẹp, hiện đại như mẫu, màu theo hệ thống") — dùng CHUNG cho 3 trang
         chính sách (Điều khoản / Bảo mật / Hoàn tiền). Mỗi mục có số thứ tự; mục có 'split' → 2 thẻ Quyền / Nghĩa vụ, mục có 'items'
         → danh sách tick, còn lại là đoạn văn. Màu theo hệ thống (xanh #126F91, chữ #123B68). CSS thường, tiền tố pol-. --}}
    <style>
        /* SỬA 3/11 (khách: "cho vừa khung, thẳng hàng") — trải hết bề ngang, lề trái/phải ĐÚNG bằng lề thanh menu trên cùng
           (partials/nav-public: px-3 / sm:px-5 / lg:px-4 / 2xl:px-8) nên mép khung thẳng hàng với logo và ảnh đại diện. */
        .pol-wrap { width: 100%; margin: 0; padding: 16px 12px 56px; }
        @media (min-width: 640px) { .pol-wrap { padding: 20px 20px 64px; } }
        @media (min-width: 1024px) { .pol-wrap { padding: 20px 16px 72px; } }
        @media (min-width: 1536px) { .pol-wrap { padding: 24px 32px 72px; } }
        .pol-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 14px; font-size: 13px; font-weight: 600; color: #607A90; text-decoration: none; }
        .pol-back:hover { color: #126F91; }
        .pol-card { border: 1px solid #DCE9F1; border-radius: 24px; background: #fff; box-shadow: 0 10px 34px rgba(18, 59, 104, .06); overflow: hidden; overflow: clip; }
        .pol-head { position: relative; padding: 18px 18px 16px; border-bottom: 1px solid #E8F0F5;
            background: radial-gradient(120% 140% at 100% 0%, #E3F2F8 0%, rgba(227, 242, 248, 0) 55%), linear-gradient(180deg, #F7FBFD, #fff); }
        @media (min-width: 768px) { .pol-head { padding: 22px 28px 18px; } }
        .pol-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 5px; border-radius: 999px; background: #EAF5F8; color: #126F91; font-size: 11px; font-weight: 700; }
        .pol-badge i { display: grid; place-items: center; width: 18px; height: 18px; border-radius: 8px; background: #126F91; color: #fff; }
        .pol-badge svg { width: 11px; height: 11px; }
        .pol-title { margin: 10px 0 0; font-size: 18px; line-height: 1.25; font-weight: 800; letter-spacing: -.01em; color: #123B68; }
        @media (min-width: 768px) { .pol-title { font-size: 20px; } }
        .pol-desc { margin: 6px 0 0; max-width: 960px; font-size: 12.5px; line-height: 1.6; color: #52687B; }
        .pol-date { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 11px; font-weight: 600; color: #7B8EA0; }
        .pol-date svg { width: 13px; height: 13px; }

        .pol-body { padding: 4px 18px 20px; }
        @media (min-width: 768px) { .pol-body { padding: 4px 28px 24px; } }
        .pol-sec { padding: 14px 0; border-bottom: 1px dashed #E3ECF2; scroll-margin-top: 90px; }
        .pol-sec:last-child { border-bottom: 0; }
        .pol-h { display: flex; align-items: center; gap: 9px; margin: 0 0 7px; font-size: 15px; font-weight: 800; color: #123B68; }
        .pol-num { display: grid; place-items: center; width: 24px; height: 24px; flex: none; border-radius: 8px; background: linear-gradient(135deg, #1C86AE, #126F91); color: #fff; font-size: 12px; font-weight: 800; box-shadow: 0 3px 8px rgba(18, 111, 145, .22); }
        .pol-p { margin: 0; font-size: 13px; line-height: 1.7; color: #3D566B; }
        .pol-intro { margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #3D566B; }
        .pol-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 6px; }
        .pol-list li { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.6; color: #3D566B; }
        .pol-list li svg { width: 15px; height: 15px; flex: none; margin-top: 2px; color: #126F91; }
        .pol-split { display: grid; gap: 12px; }
        @media (min-width: 768px) { .pol-split { grid-template-columns: 1fr 1fr; } }
        .pol-box { padding: 12px 14px; border-radius: 16px; border: 1px solid #D6EAF1; background: linear-gradient(180deg, #F3FAFC, #fff); }
        .pol-box.is-warn { border-color: #F2E1BC; background: linear-gradient(180deg, #FFF9EC, #fff); }
        .pol-box h3 { display: flex; align-items: center; gap: 7px; margin: 0 0 8px; font-size: 13.5px; font-weight: 800; color: #126F91; }
        .pol-box.is-warn h3 { color: #A1661B; }
        .pol-box h3 svg { width: 16px; height: 16px; }
        .pol-box .pol-list li { font-size: 12.5px; }
        .pol-box .pol-list li svg { width: 14px; height: 14px; color: #2F8A6B; }
        .pol-box.is-warn .pol-list li svg { color: #C27C1E; }

        .pol-note { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 12px; padding: 12px 16px; border-radius: 18px; border: 1px solid #CFE6EF;
            background: linear-gradient(110deg, #EAF5F8 0%, #F4F8FF 60%, #EEF4FF 100%); }
        .pol-note-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #fff; color: #126F91; box-shadow: 0 6px 16px rgba(18, 111, 145, .14); }
        .pol-note-ic svg { width: 20px; height: 20px; }
        .pol-note-tx { flex: 1 1 280px; min-width: 0; }
        .pol-note-tx b { display: block; font-size: 13.5px; font-weight: 800; color: #123B68; }
        .pol-note-tx p { margin: 3px 0 0; font-size: 12px; line-height: 1.6; color: #52687B; }
        .pol-cta { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 11px; background: #126F91; color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; box-shadow: 0 8px 18px rgba(18, 111, 145, .25); transition: background .15s, transform .15s; }
        .pol-cta:hover { background: #0D5B77; transform: translateY(-1px); }
        .pol-cta svg { width: 14px; height: 14px; }
        .pol-empty { padding: 28px 0; text-align: center; font-size: 14px; color: #94A3B8; }
        .pol-p + .pol-intro { margin-top: 8px; }
        /* SỬA 4/11 (khách: "bỏ mục lục ngang, chia layout cho đẹp") — trang nhiều mục: màn rộng chia 2 cột — cột trái là mục lục
           dính theo khi cuộn (mục đang đọc được tô sáng), cột phải là nội dung. Màn nhỏ: chỉ nội dung, ẩn mục lục. */
        .pol-layout { display: block; }
        .pol-side { display: none; }
        @media (min-width: 1024px) {
            .pol-layout.has-side { display: grid; grid-template-columns: 250px minmax(0, 1fr); }
            .pol-layout.has-side .pol-side { display: block; border-right: 1px solid #E8F0F5; background: #FBFDFE; }
            .pol-layout.has-side .pol-body { padding-left: 28px; }
        }
        @media (min-width: 1536px) { .pol-layout.has-side { grid-template-columns: 280px minmax(0, 1fr); } }
        .pol-side-in { position: sticky; top: 84px; max-height: calc(100vh - 100px); overflow-y: auto; padding: 16px 12px 16px 16px; }
        .pol-side-h { display: flex; align-items: center; gap: 6px; margin: 0 0 8px 6px; font-size: 11px; font-weight: 800; color: #7B8EA0; text-transform: uppercase; letter-spacing: .06em; }
        .pol-side-h svg { width: 14px; height: 14px; }
        .pol-side ol { margin: 0; padding: 0; list-style: none; display: grid; gap: 2px; }
        .pol-side a { display: flex; align-items: baseline; gap: 8px; padding: 6px 8px; border-radius: 10px; border-left: 3px solid transparent; font-size: 12.5px; line-height: 1.4; color: #4A6378; text-decoration: none; transition: background .15s, color .15s; }
        .pol-side a:hover { background: #EEF6F9; color: #126F91; }
        .pol-side a b { flex: none; min-width: 16px; font-weight: 800; color: #9AB0C0; }
        .pol-side a.is-on { background: #EAF5F8; border-left-color: #126F91; color: #0D5B77; font-weight: 700; }
        .pol-side a.is-on b { color: #126F91; }
    </style>

    <div class="pol-wrap">
        {{-- SỬA 3/11 (khách: "bỏ nút Quay lại Thông tin"; "chữ đồng bộ trang chủ" — thang chữ theo trang chủ: tiêu đề 18–20, mục 15, nội dung 13, phụ 11) --}}
        <article class="pol-card">
            <header class="pol-head">
                <span class="pol-badge"><i><x-lucide :name="$polIcon" /></i>{{ $title }}</span>
                <h1 class="pol-title">{{ $slug === 'dieu-khoan' ? 'Điều khoản sử dụng dịch vụ' : $title }}</h1>
                @if ($desc)
                    <p class="pol-desc">{{ $desc }}</p>
                @endif
                @if ($updated)
                    <p class="pol-date"><x-lucide name="calendar-days" /> Cập nhật lần cuối: {{ $updated }}</p>
                @endif
            </header>

            @php $polSide = count($sections) >= 6; @endphp
            <div class="pol-layout {{ $polSide ? 'has-side' : '' }}">
            @if ($polSide)
                <aside class="pol-side" aria-label="Mục lục">
                    <div class="pol-side-in">
                        <p class="pol-side-h"><x-lucide name="list" />Mục lục</p>
                        <ol>
                            @foreach ($sections as $i => $s)
                                <li><a href="#muc-{{ $i + 1 }}" data-pol-toc="muc-{{ $i + 1 }}"><b>{{ $i + 1 }}</b><span>{{ $s['heading'] }}</span></a></li>
                            @endforeach
                        </ol>
                    </div>
                </aside>
            @endif

            <div class="pol-body">
                @forelse ($sections as $i => $s)
                    <section class="pol-sec" id="muc-{{ $i + 1 }}">
                        <h2 class="pol-h"><span class="pol-num">{{ $i + 1 }}</span>{{ $s['heading'] }}</h2>

                        @if (! empty($s['body']))
                            <p class="pol-p">{{ $s['body'] }}</p>
                        @endif

                        @if (! empty($s['split']))
                            <div class="pol-split">
                                @foreach ($s['split'] as $box)
                                    @php $warn = ($box['tone'] ?? '') === 'warn'; @endphp
                                    <div class="pol-box {{ $warn ? 'is-warn' : '' }}">
                                        <h3><x-lucide :name="$warn ? 'circle-alert' : 'check-circle-2'" />{{ $box['title'] }}</h3>
                                        <ul class="pol-list">
                                            @foreach ($box['items'] ?? [] as $it)
                                                <li><x-lucide name="check" /><span>{{ $it }}</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (! empty($s['items']))
                            @if (! empty($s['intro']))
                                <p class="pol-intro">{{ $s['intro'] }}</p>
                            @endif
                            <ul class="pol-list">
                                @foreach ($s['items'] as $it)
                                    <li><x-lucide name="check-circle-2" /><span>{{ $it }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @empty
                    <p class="pol-empty">Nội dung đang được cập nhật.</p>
                @endforelse

                <aside class="pol-note" aria-label="Lưu ý quan trọng">
                    <span class="pol-note-ic"><x-lucide name="megaphone" /></span>
                    <div class="pol-note-tx">
                        <b>Lưu ý quan trọng</b>
                        <p>{{ $slug === 'dieu-khoan'
                            ? 'Bằng việc sử dụng Ôn Thi 360, bạn đồng ý với toàn bộ các điều khoản trên. Nếu có bất kỳ thắc mắc nào, vui lòng liên hệ với chúng tôi để được hỗ trợ.'
                            : 'Có câu hỏi về '.mb_strtolower($title).'? Liên hệ với chúng tôi, chúng tôi sẽ phản hồi sớm nhất có thể.' }}</p>
                    </div>
                    <a href="{{ route('info.index') }}#lien-he" class="pol-cta"><x-lucide name="mail" /> Liên hệ ngay <x-lucide name="arrow-right" /></a>
                </aside>
            </div>
            </div>{{-- /.pol-layout --}}
        </article>
    </div>

    @if ($polSide ?? false)
        <script>
            // Tô sáng mục đang đọc trong cột mục lục.
            (function () {
                var links = document.querySelectorAll('[data-pol-toc]');
                if (!links.length || !('IntersectionObserver' in window)) return;
                var map = {};
                links.forEach(function (a) { map[a.getAttribute('data-pol-toc')] = a; });
                var on = function (id) { links.forEach(function (a) { a.classList.toggle('is-on', a.getAttribute('data-pol-toc') === id); }); };
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) { if (e.isIntersecting && map[e.target.id]) on(e.target.id); });
                }, { rootMargin: '-90px 0px -65% 0px', threshold: 0 });
                document.querySelectorAll('.pol-sec[id]').forEach(function (sec) { io.observe(sec); });
                on(links[0].getAttribute('data-pol-toc'));
            })();
        </script>
    @endif
@endsection

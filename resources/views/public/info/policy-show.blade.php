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
        .pol-card { border: 1px solid #DCE9F1; border-radius: 24px; background: #fff; box-shadow: 0 10px 34px rgba(18, 59, 104, .06); overflow: hidden; }
        .pol-head { position: relative; padding: 26px 22px 22px; border-bottom: 1px solid #E8F0F5;
            background: radial-gradient(120% 140% at 100% 0%, #E3F2F8 0%, rgba(227, 242, 248, 0) 55%), linear-gradient(180deg, #F7FBFD, #fff); }
        @media (min-width: 768px) { .pol-head { padding: 30px 40px 24px; } }
        .pol-badge { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px 6px 7px; border-radius: 999px; background: #EAF5F8; color: #126F91; font-size: 12.5px; font-weight: 700; }
        .pol-badge i { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 8px; background: #126F91; color: #fff; }
        .pol-badge svg { width: 13px; height: 13px; }
        .pol-title { margin: 12px 0 0; font-size: 22px; line-height: 1.25; font-weight: 800; letter-spacing: -.01em; color: #123B68; }
        @media (min-width: 768px) { .pol-title { font-size: 28px; } }
        .pol-desc { margin: 8px 0 0; max-width: 960px; font-size: 14px; line-height: 1.6; color: #52687B; }
        .pol-date { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 12.5px; font-weight: 600; color: #7B8EA0; }
        .pol-date svg { width: 14px; height: 14px; }

        .pol-body { padding: 8px 22px 26px; }
        @media (min-width: 768px) { .pol-body { padding: 8px 40px 32px; } }
        .pol-sec { padding: 20px 0; border-bottom: 1px dashed #E3ECF2; scroll-margin-top: 90px; }
        .pol-sec:last-child { border-bottom: 0; }
        .pol-h { display: flex; align-items: center; gap: 12px; margin: 0 0 10px; font-size: 17px; font-weight: 800; color: #123B68; }
        .pol-num { display: grid; place-items: center; width: 28px; height: 28px; flex: none; border-radius: 9px; background: linear-gradient(135deg, #1C86AE, #126F91); color: #fff; font-size: 13px; font-weight: 800; box-shadow: 0 4px 10px rgba(18, 111, 145, .25); }
        .pol-p { margin: 0; font-size: 14px; line-height: 1.75; color: #3D566B; }
        .pol-intro { margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #3D566B; }
        .pol-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
        .pol-list li { display: flex; align-items: flex-start; gap: 9px; font-size: 14px; line-height: 1.6; color: #3D566B; }
        .pol-list li svg { width: 17px; height: 17px; flex: none; margin-top: 2px; color: #126F91; }
        .pol-split { display: grid; gap: 14px; }
        @media (min-width: 768px) { .pol-split { grid-template-columns: 1fr 1fr; } }
        .pol-box { padding: 16px 18px; border-radius: 18px; border: 1px solid #D6EAF1; background: linear-gradient(180deg, #F3FAFC, #fff); }
        .pol-box.is-warn { border-color: #F2E1BC; background: linear-gradient(180deg, #FFF9EC, #fff); }
        .pol-box h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 10px; font-size: 14.5px; font-weight: 800; color: #126F91; }
        .pol-box.is-warn h3 { color: #A1661B; }
        .pol-box h3 svg { width: 20px; height: 20px; }
        .pol-box .pol-list li { font-size: 13.5px; }
        .pol-box .pol-list li svg { width: 16px; height: 16px; color: #2F8A6B; }
        .pol-box.is-warn .pol-list li svg { color: #C27C1E; }

        .pol-note { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-top: 20px; padding: 18px 20px; border-radius: 22px; border: 1px solid #CFE6EF;
            background: linear-gradient(110deg, #EAF5F8 0%, #F4F8FF 60%, #EEF4FF 100%); }
        .pol-note-ic { display: grid; place-items: center; width: 52px; height: 52px; flex: none; border-radius: 16px; background: #fff; color: #126F91; box-shadow: 0 6px 16px rgba(18, 111, 145, .14); }
        .pol-note-ic svg { width: 26px; height: 26px; }
        .pol-note-tx { flex: 1 1 280px; min-width: 0; }
        .pol-note-tx b { display: block; font-size: 14.5px; font-weight: 800; color: #123B68; }
        .pol-note-tx p { margin: 4px 0 0; font-size: 13px; line-height: 1.6; color: #52687B; }
        .pol-cta { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px; border-radius: 12px; background: #126F91; color: #fff; font-size: 13px; font-weight: 800; text-decoration: none; box-shadow: 0 8px 18px rgba(18, 111, 145, .25); transition: background .15s, transform .15s; }
        .pol-cta:hover { background: #0D5B77; transform: translateY(-1px); }
        .pol-cta svg { width: 16px; height: 16px; }
        .pol-empty { padding: 28px 0; text-align: center; font-size: 14px; color: #94A3B8; }
    </style>

    <div class="pol-wrap">
        {{-- SỬA 3/11 (khách: "bỏ nút Quay lại Thông tin") --}}
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
        </article>
    </div>
@endsection

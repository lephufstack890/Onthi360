@extends('layouts.admin')

@section('title', 'Tổng quan quản trị')
@section('page-title', 'Tổng quan')

@section('content')
    {{--
      SỬA 8/10 (khách: "dựa vào source mới nhất, cập nhật lại UI màn tổng quan của admin, dữ liệu
      lấy từ database đổ vào").

      DỰNG LẠI THEO education-main/src/components/RoleWorkspace.jsx — Hero() cùng AdminOverview():

        1. Banner "Trung tâm vận hành Ôn Thi 360" + nút "Mở báo cáo"
        2. Dải 4 thẻ số liệu (Người dùng hoạt động / Chờ phê duyệt / Quyền sắp hết hạn / Đánh giá cần xử lý)
        3. Lưới [1.35fr 1fr]: trái "Việc cần xử lý", phải "Hoạt động học tập"
        4. (giữ lại từ bản cũ, bản mẫu không có) "Hoạt động gần đây" lấy từ nhật ký kiểm toán — đây là
           chỗ duy nhất hiện dữ liệu đó, bỏ đi là mất thông tin; muốn bỏ chỉ cần xoá khối cuối trang.

      Mọi con số do App\Services\Admin\DashboardService::dashboardData() đếm từ DB (xem chú thích
      ở đó về cách tính từng thẻ). View không truy vấn gì thêm.

      Server không build lại Tailwind nên toàn bộ kiểu của trang nằm trong khối <style> bên dưới
      (tiền tố .ad-) — không phụ thuộc class nào có/không có trong file CSS đã biên dịch.
    --}}
    @php
        $metrics = $metrics ?? [];
        $tasks = $tasks ?? [];
        $learning = $learning ?? ['value' => '0', 'unit' => null, 'trend' => 'flat', 'caption' => '', 'headline' => ''];
        $activity = $activity ?? [];
        $openTasks = collect($tasks)->sum('count');
    @endphp

    <style>
        .ad-page{display:flex;flex-direction:column;gap:20px;min-width:0}
        /* Banner */
        .ad-hero{position:relative;overflow:hidden;min-height:188px;border-radius:24px;border:1px solid #e0f2fe;background:#0d5faf;box-shadow:0 7px 20px rgba(0,95,180,.09)}
        .ad-hero__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;user-select:none}
        .ad-hero__shade{position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(7,89,168,.95),rgba(9,118,201,.72) 50%,rgba(9,118,201,.10))}
        .ad-hero__body{position:relative;display:flex;flex-direction:column;gap:16px;padding:24px 20px;color:#fff;min-height:188px;justify-content:flex-end}
        .ad-hero__eyebrow{margin:0;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#e0f2fe}
        .ad-hero__title{margin:8px 0 0;font-size:24px;line-height:1.2;font-weight:700;letter-spacing:-.01em;color:#fff;word-break:break-word}
        .ad-hero__desc{margin:8px 0 0;max-width:36rem;font-size:14px;line-height:1.6;color:#f0f9ff}
        .ad-hero__btn{display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border-radius:12px;background:#fff;color:#1d4ed8;font-size:12px;font-weight:700;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.08);transition:background .15s}
        .ad-hero__btn:hover{background:#f0f9ff}
        @media (max-width:639px){.ad-hero__shade{background:linear-gradient(180deg,rgba(7,89,168,.92),rgba(9,118,201,.72))}}
        @media (min-width:640px){
            .ad-hero__body{flex-direction:row;align-items:flex-end;justify-content:space-between;padding:24px 28px}
            .ad-hero__title{font-size:30px}
        }
        /* Thẻ số liệu */
        .ad-metrics{display:grid;gap:16px;grid-template-columns:1fr}
        @media (min-width:640px){.ad-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (min-width:1280px){.ad-metrics{grid-template-columns:repeat(4,minmax(0,1fr))}}
        .ad-card{min-width:0;border-radius:24px;border:1px solid #e0f2fe;background:#fff;box-shadow:0 3px 12px rgba(25,90,150,.04)}
        .ad-metric{display:flex;flex-direction:column;justify-content:space-between;gap:20px;padding:20px;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s}
        .ad-metric:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(25,90,150,.09)}
        @media (min-width:640px){.ad-metric{min-height:156px}}
        .ad-metric__top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
        .ad-metric__label{margin:0;min-width:0;font-size:14px;font-weight:600;line-height:1.35;color:#475569}
        .ad-metric__icon{display:grid;place-items:center;flex-shrink:0;width:40px;height:40px;border-radius:16px;border:1px solid}
        .ad-metric__icon svg{width:19px;height:19px}
        .ad-tone-blue{background:#eff6ff;border-color:#dbeafe;color:#1d4ed8}
        .ad-tone-amber{background:#fffbeb;border-color:#fef3c7;color:#b45309}
        .ad-tone-violet{background:#f5f3ff;border-color:#ede9fe;color:#6d28d9}
        .ad-tone-emerald{background:#ecfdf5;border-color:#d1fae5;color:#047857}
        .ad-metric__value{display:block;font-size:32px;line-height:1;font-weight:800;letter-spacing:-.02em;color:#0f172a}
        .ad-metric__detail{margin:12px 0 0;font-size:13px;line-height:1.4;color:#64748b}
        /* Lưới chính */
        .ad-main{display:grid;gap:16px;grid-template-columns:minmax(0,1fr)}
        @media (min-width:1180px){.ad-main{grid-template-columns:minmax(0,1.35fr) minmax(320px,1fr)}}
        .ad-panel{padding:20px;display:flex;flex-direction:column}
        @media (min-width:640px){.ad-panel{padding:24px}}
        .ad-panel__head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
        .ad-panel__tile{display:grid;place-items:center;flex-shrink:0;width:40px;height:40px;border-radius:16px}
        .ad-panel__tile svg{width:20px;height:20px}
        .ad-panel__title{margin:0;font-size:18px;font-weight:700;line-height:1.25;color:#0f172a}
        .ad-panel__sub{margin:2px 0 0;font-size:13px;color:#64748b}
        /* Việc cần xử lý */
        .ad-tasks{list-style:none;margin:0;padding:0}
        .ad-tasks li + li{border-top:1px solid #f1f5f9}
        .ad-task{display:flex;align-items:center;gap:16px;min-height:66px;padding:0 8px;border-radius:12px;text-decoration:none;color:inherit;transition:background .15s}
        .ad-task:hover{background:#f0f9ff}
        .ad-task__count{display:grid;place-items:center;flex-shrink:0;min-width:40px;height:40px;padding:0 8px;border-radius:12px;background:#eff6ff;color:#1d4ed8;font-size:16px;font-weight:700;font-variant-numeric:tabular-nums}
        .ad-task.is-empty .ad-task__count{background:#f8fafc;color:#94a3b8}
        .ad-task.is-empty .ad-task__label{color:#94a3b8}
        .ad-task__label{min-width:0;flex:1;font-size:15px;font-weight:600;line-height:1.35;color:#334155}
        .ad-task__go{flex-shrink:0;width:19px;height:19px;color:#94a3b8;transition:transform .15s,color .15s}
        .ad-task:hover .ad-task__go{transform:translateX(4px);color:#2563eb}
        .ad-allclear{margin:14px 8px 0;padding:10px 14px;border-radius:12px;background:#ecfdf5;color:#047857;font-size:13px;font-weight:600}
        /* Hoạt động học tập */
        .ad-learn{margin-top:24px;padding:24px 20px;border-radius:16px;background:linear-gradient(135deg,#f0f9ff,#eff6ff)}
        .ad-learn__label{margin:0;font-size:14px;font-weight:600;color:#475569}
        .ad-learn__value{display:block;margin-top:8px;font-size:48px;line-height:1.05;font-weight:800;letter-spacing:-.02em;color:#1d4ed8}
        .ad-learn__value small{font-size:20px;font-weight:700;margin-left:6px;color:#475569;letter-spacing:0}
        .ad-learn.is-down .ad-learn__value{color:#be123c}
        .ad-learn.is-flat .ad-learn__value{color:#334155}
        .ad-learn__cap{margin:12px 0 0;font-size:14px;line-height:1.6;color:#475569}
        .ad-link{display:inline-flex;align-items:center;gap:4px;align-self:flex-start;margin-top:auto;padding-top:20px;min-height:44px;font-size:14px;font-weight:700;color:#1d4ed8;text-decoration:none}
        .ad-link:hover{color:#1e3a8a}
        .ad-link svg{width:17px;height:17px}
        /* Hoạt động gần đây */
        .ad-feed{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px}
        .ad-feed li{display:flex;align-items:flex-start;gap:12px;font-size:13px}
        .ad-feed__dot{width:8px;height:8px;border-radius:999px;background:#3b82f6;margin-top:6px;flex-shrink:0}
        .ad-feed__text{margin:0;color:#334155;word-break:break-word}
        .ad-feed__meta{margin:2px 0 0;font-size:12px;color:#94a3b8}
    </style>

    <div class="ad-page">
        {{-- 1. Banner --}}
        <section class="ad-hero" aria-label="Trung tâm vận hành">
            <img class="ad-hero__img" src="{{ asset('assets/workspace-admin-hero.jpg') }}" alt="" loading="eager" decoding="async">
            <div class="ad-hero__shade"></div>
            <div class="ad-hero__body">
                <div style="min-width:0">
                    <p class="ad-hero__eyebrow">Quản trị &amp; kiểm duyệt</p>
                    <h1 class="ad-hero__title">Trung tâm vận hành Ôn Thi 360</h1>
                    <p class="ad-hero__desc">Theo dõi những việc cần xử lý và tình hình hoạt động của hệ thống.</p>
                </div>
                <div>
                    <a href="{{ route('admin.reports.index') }}" class="ad-hero__btn">Mở báo cáo</a>
                </div>
            </div>
        </section>

        {{-- 2. Bốn thẻ số liệu --}}
        <section class="ad-metrics" aria-label="Số liệu tổng quan">
            @foreach ($metrics as $m)
                <a href="{{ $m['href'] }}" class="ad-card ad-metric">
                    <div class="ad-metric__top">
                        <h2 class="ad-metric__label">{{ $m['label'] }}</h2>
                        <span class="ad-metric__icon ad-tone-{{ $m['tone'] }}"><x-lucide :name="$m['icon']" /></span>
                    </div>
                    <div>
                        <strong class="ad-metric__value">{{ $m['value'] }}</strong>
                        <p class="ad-metric__detail">{{ $m['detail'] }}</p>
                    </div>
                </a>
            @endforeach
        </section>

        {{-- 3. Việc cần xử lý + Hoạt động học tập --}}
        <div class="ad-main">
            <section class="ad-card ad-panel" id="viec-can-xu-ly" aria-labelledby="ad-tasks-title">
                <div class="ad-panel__head">
                    <span class="ad-panel__tile ad-tone-blue"><x-lucide name="clock-3" /></span>
                    <div>
                        <h2 class="ad-panel__title" id="ad-tasks-title">Việc cần xử lý</h2>
                        <p class="ad-panel__sub">Chọn một mục để bắt đầu</p>
                    </div>
                </div>
                <ul class="ad-tasks">
                    @foreach ($tasks as $t)
                        <li>
                            <a href="{{ $t['href'] }}" class="ad-task {{ $t['count'] > 0 ? '' : 'is-empty' }}">
                                <span class="ad-task__count">{{ $t['count'] < 100 ? str_pad((string) $t['count'], 2, '0', STR_PAD_LEFT) : number_format($t['count'], 0, ',', '.') }}</span>
                                <span class="ad-task__label">{{ $t['label'] }}</span>
                                <x-lucide name="chevron-right" class="ad-task__go" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($openTasks === 0)
                    <p class="ad-allclear">Không còn việc nào đang chờ xử lý.</p>
                @endif
            </section>

            <section class="ad-card ad-panel" aria-labelledby="ad-learn-title">
                <div class="ad-panel__head" style="margin-bottom:0">
                    <span class="ad-panel__tile ad-tone-emerald"><x-lucide name="bar-chart-3" /></span>
                    <h2 class="ad-panel__title" id="ad-learn-title">Hoạt động học tập</h2>
                </div>
                <div class="ad-learn is-{{ $learning['trend'] }}">
                    <p class="ad-learn__label">{{ $learning['headline'] }}</p>
                    <strong class="ad-learn__value">{{ $learning['value'] }}@if ($learning['unit'])<small>{{ $learning['unit'] }}</small>@endif</strong>
                    <p class="ad-learn__cap">{{ $learning['caption'] }}</p>
                </div>
                <a href="{{ route('admin.reports.index') }}" class="ad-link">Xem báo cáo <x-lucide name="chevron-right" /></a>
            </section>
        </div>

        {{-- 4. Hoạt động gần đây (nhật ký kiểm toán) --}}
        {{-- <section class="ad-card ad-panel" aria-labelledby="ad-feed-title">
            <div class="ad-panel__head">
                <span class="ad-panel__tile ad-tone-violet"><x-lucide name="history" /></span>
                <div>
                    <h2 class="ad-panel__title" id="ad-feed-title">Hoạt động gần đây</h2>
                    <p class="ad-panel__sub">Các thao tác mới nhất trong nhật ký hệ thống</p>
                </div>
            </div>
            <ul class="ad-feed">
                @forelse ($activity as $a)
                    <li>
                        <span class="ad-feed__dot"></span>
                        <div>
                            <p class="ad-feed__text">{{ $a['text'] }}</p>
                            <p class="ad-feed__meta">{{ $a['time'] }} · {{ $a['actor'] }}</p>
                        </div>
                    </li>
                @empty
                    <li style="color:#94a3b8">Chưa có hoạt động nào được ghi nhận.</li>
                @endforelse
            </ul>
        </section> --}}
    </div>
@endsection

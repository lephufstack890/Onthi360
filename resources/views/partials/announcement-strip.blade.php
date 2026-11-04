{{-- SỬA 4/11 (khách: "hiển thị ra thông báo") — dải "Thông báo mới" đầu trang Tổng quan của Học sinh / Phụ huynh / Giáo viên:
     tối đa 3 thông báo CHƯA ĐỌC do Quản trị (SystemAnnouncementPublished) hoặc giáo viên (ClassAnnouncementPublished) gửi.
     Bấm "Xem" mở trang đọc đủ nội dung (tự đánh dấu đã đọc nên dải tự biến mất). Include ở layouts/workspace. CSS tiền tố ans-. --}}
@php
    $ansItems = [];
    $ansUser = auth()->user();
    if ($ansUser) {
        try {
            $ansItems = $ansUser->unreadNotifications()
                ->whereIn('type', [\App\Notifications\SystemAnnouncementPublished::class, \App\Notifications\ClassAnnouncementPublished::class])
                ->limit(3)->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'icon' => $n->data['icon'] ?? '📣',
                    'title' => $n->data['title'] ?? 'Thông báo',
                    'text' => $n->data['text'] ?? '',
                    'imp' => ($n->data['level'] ?? null) === 'important',
                    'time' => $n->created_at?->diffForHumans(),
                ])->all();
        } catch (\Throwable $e) {
            $ansItems = [];
        }
    }
@endphp
@if (! empty($ansItems))
    <style>
        .ans { min-width: 0; border: 1px solid #CFE6EF; border-radius: 20px; background: linear-gradient(110deg, #EAF5F8 0%, #F4F8FF 60%, #EEF4FF 100%); padding: 12px 14px; }
        .ans-h { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: 13px; font-weight: 800; color: #123B68; }
        .ans-h svg { width: 16px; height: 16px; color: #126F91; }
        .ans-list { display: grid; grid-template-columns: minmax(0, 1fr); gap: 6px; }
        .ans-it { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 14px; background: #fff; border: 1px solid #E0EEF4; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
        .ans-it:hover { border-color: #9FCFE0; box-shadow: 0 4px 12px rgba(18, 111, 145, .08); }
        .ans-it.is-imp { border-color: #F2D9A6; background: #FFFBF2; }
        .ans-ic { display: grid; place-items: center; width: 32px; height: 32px; flex: none; border-radius: 10px; background: #EAF5F8; font-size: 16px; }
        .ans-it.is-imp .ans-ic { background: #FDECC8; }
        .ans-tx { flex: 1; min-width: 0; }
        .ans-tx b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 800; color: #123B68; }
        .ans-tx span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #5C7287; }
        .ans-go { flex: none; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 800; color: #126F91; }
        .ans-go svg { width: 13px; height: 13px; }
        .ans-time { flex: none; font-size: 11px; color: #94A3B8; }
        @media (max-width: 640px) { .ans-time { display: none; } }
    </style>
    <section class="ans" aria-label="Thông báo mới">
        <h2 class="ans-h"><x-lucide name="megaphone" />Thông báo mới dành cho bạn</h2>
        <div class="ans-list">
            @foreach ($ansItems as $it)
                <a href="{{ route('notifications.show', $it['id']) }}" class="ans-it {{ $it['imp'] ? 'is-imp' : '' }}">
                    <span class="ans-ic" aria-hidden="true">{{ $it['icon'] }}</span>
                    <span class="ans-tx"><b>{{ $it['title'] }}</b>@if ($it['text'])<span>{{ $it['text'] }}</span>@endif</span>
                    <span class="ans-time">{{ $it['time'] }}</span>
                    <span class="ans-go">Xem <x-lucide name="arrow-right" /></span>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- SỬA 6/11 — tab "Tiến trình học" của học sinh: các bước giáo viên đã mở khoá. CSS tiền tố spg-.
     SỬA 7/11 — thêm khối "Sắp mở": bước thầy cô đã HẸN GIỜ mở (chỉ tên + giờ mở, nội dung vẫn ẩn tới lúc mở); link bài tập trong
     bước mở thẳng tab Bài tập / Đề của lớp; chữ / độ đậm đồng bộ với các tab khác. --}}
@php
    $progressSteps = $progressSteps ?? [];
    $progressUpcoming = $progressUpcoming ?? [];
@endphp
<section class="spg">
    <div class="spg-head">
        <span class="spg-ic"><x-lucide name="layers" /></span>
        <div><h2>Tiến trình học</h2><p>Các bài thầy cô đã mở cho lớp — học lần lượt từ trên xuống nhé.</p></div>
        @if (count($progressSteps) + count($progressUpcoming) > 0)
            <span class="spg-count">{{ count($progressSteps) }} bước đã mở</span>
        @endif
    </div>
    @forelse ($progressSteps as $i => $s)
        <article class="spg-step {{ $s['current'] ? 'now' : '' }}">
            <span class="spg-no">@if ($s['current'])<x-lucide name="play" />@else<x-lucide name="check" />@endif</span>
            <div class="spg-body">
                <div class="spg-top"><b>Bước {{ $i + 1 }}. {{ $s['title'] }}</b>@if ($s['current'])<em>Đang học</em>@endif</div>
                @if ($s['description'] !== '')<p class="spg-desc">{{ $s['description'] }}</p>@endif
                @if (! empty($s['items']))
                    <div class="spg-items">
                        @foreach ($s['items'] as $it)
                            <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => $it['type'] === 'material' ? 'materials' : 'assign']) }}" class="spg-item {{ $it['type'] }}">
                                <x-lucide :name="$it['type'] === 'material' ? 'book-open' : 'clipboard-list'" />{{ $it['title'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
                @if ($s['unlockedAt'])<small class="spg-meta">Mở lúc {{ $s['unlockedAt'] }}</small>@endif
            </div>
        </article>
    @empty
        <div class="spg-empty"><x-lucide name="lock" /><p>Thầy cô chưa mở bài nào trong tiến trình. Khi có bài mới, em sẽ nhận thông báo.</p></div>
    @endforelse

    @if ($progressUpcoming !== [])
        <h3 class="spg-sub"><x-lucide name="clock" />Sắp mở</h3>
        @foreach ($progressUpcoming as $u)
            <article class="spg-step lock">
                <span class="spg-no"><x-lucide name="lock" /></span>
                <div class="spg-body">
                    <div class="spg-top"><b>{{ $u['title'] }}</b></div>
                    <small class="spg-when">Tự mở lúc <strong>{{ $u['when'] }}</strong>@if ($u['in']) · còn {{ $u['in'] }}@endif</small>
                </div>
            </article>
        @endforeach
    @endif
</section>
<style>
    .spg { padding: 18px; border: 1px solid #DDEAF0; border-radius: 20px; background: #fff; box-shadow: 0 6px 18px rgba(15, 42, 74, .04); }
    .spg-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .spg-head > div { flex: 1; min-width: 0; }
    .spg-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: #123B68; }
    .spg-head p { margin: 2px 0 0; font-size: 12px; color: #61798B; }
    .spg-count { padding: 3px 10px; border-radius: 999px; background: #F3EEFF; font-size: 12px; font-weight: 700; color: #6D28D9; white-space: nowrap; }
    .spg-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #F3EEFF; color: #7C3AED; }
    .spg-ic svg { width: 19px; height: 19px; }
    .spg-step { display: flex; gap: 12px; padding: 14px; border: 1px solid #DDEFE4; border-radius: 16px; background: #F7FCF9; }
    .spg-step + .spg-step { margin-top: 10px; }
    .spg-step.now { border-color: #93C5FD; background: #F5F9FF; box-shadow: 0 0 0 4px rgba(59, 130, 246, .08); }
    .spg-step.lock { border-color: #E5E7EB; border-style: dashed; background: #FAFBFC; }
    .spg-no { display: grid; place-items: center; width: 32px; height: 32px; flex: none; border-radius: 50%; background: #DCFCE7; color: #15803D; }
    .spg-no svg { width: 15px; height: 15px; }
    .spg-step.now .spg-no { background: #DBEAFE; color: #1D4ED8; } .spg-step.lock .spg-no { background: #F1F5F9; color: #94A3B8; }
    .spg-body { flex: 1; min-width: 0; }
    .spg-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .spg-top b { font-size: 14px; font-weight: 700; color: #123B68; } .spg-step.lock .spg-top b { color: #64748B; }
    .spg-top em { padding: 1px 9px; border-radius: 999px; background: #DBEAFE; color: #1D4ED8; font-style: normal; font-size: 11px; font-weight: 700; }
    .spg-desc { margin: 4px 0 0; font-size: 13px; line-height: 1.55; color: #475569; white-space: pre-line; }
    .spg-items { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .spg-item { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 10px; background: #EEF4FF; font-size: 12px; font-weight: 700; color: #1D4ED8; text-decoration: none; }
    .spg-item:hover { filter: brightness(.97); text-decoration: underline; }
    .spg-item.assignment { background: #FFF1E6; color: #C2410C; }
    .spg-item svg { width: 14px; height: 14px; }
    .spg-meta { display: block; margin-top: 6px; font-size: 12px; color: #94A3B8; }
    .spg-when { display: block; margin-top: 3px; font-size: 12px; color: #6D28D9; } .spg-when strong { font-weight: 700; }
    .spg-sub { display: flex; align-items: center; gap: 6px; margin: 18px 0 10px; font-size: 13px; font-weight: 700; color: #64748B; }
    .spg-sub svg { width: 15px; height: 15px; }
    .spg-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 30px 12px; border: 1px dashed #D5E1EA; border-radius: 14px; text-align: center; color: #8093A8; font-size: 13px; }
    .spg-empty svg { width: 22px; height: 22px; }
</style>

{{--
    SỬA 3/11 (khách: "đề có nhiều mã đề — bấm Bắt đầu làm bài thì hiện popup chọn mã đề rồi vào làm; ngoài trang phải
    nhìn ra đề có mã đề") — các mảnh giao diện NHÓM MÃ ĐỀ ở trang chi tiết đề (public/practice/exam).
    Dữ liệu: $variants (App\Services\ExamVariantService::forViewer) — rỗng thì KHÔNG in gì, trang y như cũ.
    $evPart: 'bar' (dãy mã đề trên khung xem đề) | 'fact' (dòng "Mã đề" ở Thông tin đề thi) | 'mine' (kết quả theo mã)
             | 'dialog' (CSS + popup chọn mã + script).
--}}
@php
    $evList = $variants ?? [];
    $evFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp
@if (count($evList) > 1)
    @if (($evPart ?? '') === 'bar')
        <nav class="evx-bar" aria-label="Các mã đề của đề này">
            <span class="evx-bar-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M4 16V5a1 1 0 0 1 1-1h11"/></svg>
                Đề có {{ count($evList) }} mã đề — xem trước:
            </span>
            @foreach ($evList as $v)
                <a href="{{ $v['detailHref'] }}" class="evx-chip {{ $v['current'] ? 'is-on' : '' }}" @if ($v['current']) aria-current="page" @endif
                   title="Xem trước mã đề {{ $v['label'] }}">{{ $v['label'] }}</a>
            @endforeach
        </nav>
    @elseif (($evPart ?? '') === 'fact')
        <p class="flex items-start gap-2">
            <svg class="h-3.5 w-3.5 shrink-0" style="margin-top:2px;color:#A87530" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M4 16V5a1 1 0 0 1 1-1h11"/></svg>
            <span>Mã đề: <strong class="font-semibold text-[#123B68]">{{ collect($evList)->pluck('label')->implode(' · ') }}</strong>
                <span class="text-[11px] text-[#7B6D50]">(chọn mã khi bấm Bắt đầu làm bài)</span></span>
        </p>
    @elseif (($evPart ?? '') === 'mine')
        <ul class="evx-mine" aria-label="Kết quả theo từng mã đề">
            @foreach ($evList as $v)
                <li>
                    <span class="evx-mine-code">{{ $v['label'] }}</span>
                    <span class="{{ $v['myAttempts'] > 0 ? 'is-done' : '' }}">
                        {{ $v['myAttempts'] > 0 ? $v['myAttempts'].' lần · gần nhất '.($v['myLast'] !== null ? $evFmt($v['myLast']).'/'.$evFmt($v['totalPoints']) : '—') : 'Chưa làm' }}
                    </span>
                </li>
            @endforeach
        </ul>
    @elseif (($evPart ?? '') === 'dialog')
        <style>
            .evx-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0 0 8px; padding: 8px 10px; border: 1px solid #CFE3EA; border-radius: 12px; background: #F2F9FB; }
            .evx-bar-label { display: inline-flex; align-items: center; gap: 6px; margin-right: 4px; font-size: 12px; font-weight: 700; color: #126F91; }
            .evx-bar-label svg { width: 14px; height: 14px; flex: none; }
            .evx-chip { display: inline-flex; align-items: center; min-height: 28px; padding: 0 12px; border: 1px solid #CFE3EA; border-radius: 999px; background: #fff; font-size: 12px; font-weight: 700; color: #35607A; text-decoration: none; transition: background .15s, border-color .15s; }
            .evx-chip:hover { border-color: #8CC3D6; background: #EAF5F8; }
            .evx-chip.is-on { border-color: #126F91; background: #126F91; color: #fff; }
            .evx-mine { margin: 10px 0 0; padding: 8px 0 0; border-top: 1px dashed #DCE7EC; list-style: none; display: grid; gap: 4px; font-size: 11.5px; color: #61798B; }
            .evx-mine li { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
            .evx-mine-code { font-weight: 700; color: #123B68; }
            .evx-mine .is-done { font-weight: 600; color: #2F8A6B; }

            .evx-dlg { width: min(560px, calc(100vw - 24px)); max-height: calc(100dvh - 32px); padding: 0; border: 0; border-radius: 18px; background: #fff; color: #466278; box-shadow: 0 24px 60px rgba(11, 60, 120, .25); }
            .evx-dlg::backdrop { background: rgba(15, 35, 55, .45); backdrop-filter: blur(2px); }
            .evx-head { padding: 18px 20px 12px; border-bottom: 1px solid #E6EEF2; }
            .evx-head h2 { margin: 0; font-size: 17px; font-weight: 800; color: #123B68; }
            .evx-head p { margin: 4px 0 0; font-size: 12px; color: #61798B; }
            .evx-body { max-height: calc(100dvh - 220px); overflow-y: auto; padding: 14px 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; background: #F7FAFC; }
            .evx-card { position: relative; display: flex; cursor: pointer; }
            .evx-card input { position: absolute; opacity: 0; pointer-events: none; }
            .evx-card-in { display: flex; flex: 1 1 auto; flex-direction: column; gap: 4px; padding: 12px; border: 1.5px solid #DCE7EC; border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; }
            .evx-card:hover .evx-card-in { border-color: #9DC8D7; }
            .evx-card input:checked + .evx-card-in { border-color: #126F91; box-shadow: 0 0 0 3px rgba(18, 111, 145, .14); }
            .evx-card input:focus-visible + .evx-card-in { outline: 2px solid #126F91; outline-offset: 2px; }
            .evx-code { font-size: 11px; font-weight: 700; color: #61798B; text-transform: uppercase; letter-spacing: .04em; }
            .evx-code b { display: block; margin-top: 2px; font-size: 20px; letter-spacing: 0; text-transform: none; color: #123B68; }
            .evx-meta { font-size: 11.5px; color: #61798B; }
            .evx-state { align-self: flex-start; margin-top: 2px; padding: 2px 8px; border-radius: 999px; background: #EEF3F6; font-size: 10.5px; font-weight: 700; color: #61798B; }
            .evx-state.is-done { background: #E3F2EA; color: #2F7A5C; }
            .evx-tick { position: absolute; top: 10px; right: 10px; display: none; width: 20px; height: 20px; border-radius: 999px; background: #126F91; color: #fff; place-items: center; }
            .evx-card input:checked ~ .evx-tick { display: grid; }
            .evx-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 20px 16px; border-top: 1px solid #E6EEF2; }
            .evx-note { min-height: 16px; flex: 1 1 160px; font-size: 11.5px; color: #2F7A5C; font-weight: 600; }
            .evx-actions { display: flex; flex-wrap: wrap; gap: 8px; }
            .evx-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 38px; padding: 0 14px; border: 1px solid #DCE7EC; border-radius: 12px; background: #fff; font-size: 12.5px; font-weight: 700; color: #45657D; cursor: pointer; }
            .evx-btn:hover { border-color: #9DC8D7; background: #EAF5F8; }
            .evx-btn svg { width: 14px; height: 14px; }
            .evx-btn--go { border-color: #126F91; background: #126F91; color: #fff; }
            .evx-btn--go:hover { background: #0D5B77; border-color: #0D5B77; }
            .evx-note:empty { display: none; }
            .evx-btn { white-space: nowrap; }
            @media (max-width: 480px) {
                .evx-head, .evx-body, .evx-foot { padding-left: 14px; padding-right: 14px; }
                .evx-body { grid-template-columns: 1fr 1fr; }
                .evx-actions { width: 100%; flex-wrap: nowrap; }
                .evx-actions .evx-btn { flex: 1 1 auto; padding: 0 10px; }
                .evx-btn--go { flex: 2 1 auto !important; }
            }
        </style>

        <dialog id="evx-dialog" class="evx-dlg" aria-labelledby="evx-title">
            <form method="dialog" id="evx-form">
                <header class="evx-head">
                    <h2 id="evx-title">Chọn mã đề để làm bài</h2>
                    <p>{{ $exam->title }} · {{ count($evList) }} mã đề. Mỗi mã là một đề riêng, điểm và nhật ký tính riêng từng mã.</p>
                </header>
                <div class="evx-body" role="radiogroup" aria-label="Mã đề">
                    @foreach ($evList as $v)
                        <label class="evx-card">
                            <input type="radio" name="evx_pick" value="{{ $v['takeHref'] }}" data-label="{{ $v['label'] }}" @checked($v['current'])>
                            <span class="evx-card-in">
                                <span class="evx-code">Mã đề<b>{{ $v['label'] }}</b></span>
                                <span class="evx-meta">{{ $v['itemsCount'] }} câu · {{ $v['duration'] ? $v['duration'].' phút' : 'Không giới hạn' }} · {{ $evFmt($v['totalPoints']) }} điểm</span>
                                <span class="evx-state {{ $v['myAttempts'] > 0 ? 'is-done' : '' }}">
                                    {{ $v['myAttempts'] > 0 ? 'Đã làm '.$v['myAttempts'].' lần'.($v['myLast'] !== null ? ' · '.$evFmt($v['myLast']).'/'.$evFmt($v['totalPoints']) : '') : 'Chưa làm' }}
                                </span>
                            </span>
                            <span class="evx-tick" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                        </label>
                    @endforeach
                </div>
                <footer class="evx-foot">
                    <span class="evx-note" id="evx-note" role="status"></span>
                    <div class="evx-actions">
                        <button type="button" class="evx-btn" id="evx-random">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 18h1.4c1.3 0 2.5-.6 3.3-1.7l6.1-8.6c.7-1.1 2-1.7 3.3-1.7H22"/><path d="m18 2 4 4-4 4"/><path d="M2 6h1.9c1.5 0 2.9.9 3.6 2.2"/><path d="M22 18h-5.9c-1.3 0-2.6-.7-3.3-1.8l-.5-.8"/><path d="m18 14 4 4-4 4"/></svg>
                            Ngẫu nhiên
                        </button>
                        <button type="button" class="evx-btn" id="evx-cancel">Huỷ</button>
                        <button type="button" class="evx-btn evx-btn--go" id="evx-go">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3v18l15-9z"/></svg>
                            <span id="evx-go-text">Vào làm bài</span>
                        </button>
                    </div>
                </footer>
            </form>
        </dialog>

        <script>
            (function () {
                const dlg = document.getElementById('evx-dialog');
                if (! dlg) return;
                const radios = () => Array.from(dlg.querySelectorAll('input[name="evx_pick"]'));
                const picked = () => radios().find((r) => r.checked) || null;
                const note = document.getElementById('evx-note');
                const goText = document.getElementById('evx-go-text');
                const sync = () => { const p = picked(); goText.textContent = p ? 'Vào làm mã ' + p.dataset.label : 'Vào làm bài'; };

                // Nút "Bắt đầu làm bài / Làm đề" có data-ev-open: mở popup thay vì vào thẳng (không có JS thì vẫn vào mã đang xem như cũ).
                document.addEventListener('click', (e) => {
                    const a = e.target.closest ? e.target.closest('[data-ev-open]') : null;
                    if (! a || typeof dlg.showModal !== 'function') return; // trình duyệt quá cũ → đi theo link như cũ
                    e.preventDefault();
                    note.textContent = '';
                    sync();
                    dlg.showModal();
                });

                radios().forEach((r) => r.addEventListener('change', () => { note.textContent = ''; sync(); }));
                document.getElementById('evx-cancel').addEventListener('click', () => dlg.close());
                dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
                document.getElementById('evx-random').addEventListener('click', () => {
                    const list = radios();
                    if (! list.length) return;
                    const r = list[Math.floor(Math.random() * list.length)];
                    r.checked = true;
                    r.closest('.evx-card').scrollIntoView({ block: 'nearest' });
                    note.textContent = 'Đã chọn ngẫu nhiên mã ' + r.dataset.label + '.';
                    sync();
                });
                document.getElementById('evx-go').addEventListener('click', () => {
                    const p = picked();
                    if (! p) { note.textContent = 'Hãy chọn một mã đề.'; return; }
                    window.location.href = p.value;
                });
            })();
        </script>
    @endif
@endif

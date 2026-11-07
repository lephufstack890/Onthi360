{{-- SỬA 6/11 — CSS + window.tsfForm của form buổi học (tách từ tab Buổi học cũ) — dùng ở popup Thêm / Sửa buổi của tab Điểm danh. --}}
@once
<style>
    .tsf { display: grid; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #DCE6F2; }
    .tsf-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
    @media (min-width: 720px) { .tsf-row { grid-template-columns: 1fr 1fr; gap: 16px; } }
    .tsf-f { min-width: 0; }
    .tsf-lb { display: block; margin: 0 0 6px 2px; font-size: 13px; font-weight: 700; color: #334155; }
    .tsf-in { display: flex; align-items: center; gap: 8px; height: 44px; padding: 0 12px; border: 1px solid #D9E3EE; border-radius: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .tsf-in:hover { border-color: #B8C9DC; }
    .tsf-in:focus-within { border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
    .tsf-in > svg { width: 16px; height: 16px; flex: none; color: #94A3B8; }
    .tsf-in:focus-within > svg { color: #2563EB; }
    .tsf-in input, .tsf-in select { flex: 1; min-width: 0; height: 100%; padding: 0; border: 0; outline: 0; background: transparent; box-shadow: none; font: inherit; font-size: 14px; color: #0F172A; }
    .tsf-in input::placeholder { color: #A3B1C2; }
    .tsf-in input:focus, .tsf-in select:focus { box-shadow: none; outline: 0; }
    .tsf-time { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; align-items: stretch; }
    @media (min-width: 900px) { .tsf-time { grid-template-columns: minmax(0, 1fr) 36px minmax(0, 1fr); gap: 12px; } }
    .tsf-slot { display: grid; gap: 10px; padding: 14px; border: 1px solid #D1FAE5; border-radius: 16px; background: linear-gradient(160deg, #F0FDF6, #FAFFFC); }
    .tsf-slot.is-b { border-color: #FFE0E3; background: linear-gradient(160deg, #FFF4F5, #FFFCFC); }
    .tsf-slot.bad { border-color: #F87171; box-shadow: 0 0 0 3px rgba(248, 113, 113, .15); }
    .tsf-slot-h { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #047857; }
    .tsf-slot.is-b .tsf-slot-h { color: #BE123C; }
    .tsf-slot-h small { margin-left: auto; font-size: 12px; font-weight: 600; color: #64748B; }
    .tsf-dot { width: 10px; height: 10px; border-radius: 50%; background: #10B981; box-shadow: 0 0 0 4px rgba(16, 185, 129, .18); }
    .tsf-slot.is-b .tsf-dot { background: #F43F5E; box-shadow: 0 0 0 4px rgba(244, 63, 94, .16); }
    .tsf-dt { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; }
    .tsf-date input { cursor: pointer; }
    .tsf-tm { gap: 4px; padding-right: 10px; }
    .tsf-tm select { flex: none; width: auto; padding: 0 2px; appearance: none; -webkit-appearance: none; background-image: none; cursor: pointer; font-weight: 700; font-variant-numeric: tabular-nums; text-align: center; }
    .tsf-tm b { color: #94A3B8; font-weight: 700; }
    .tsf-arrow { display: none; }
    @media (min-width: 900px) { .tsf-arrow { display: grid; place-items: center; align-self: center; width: 36px; height: 36px; border-radius: 50%; border: 1px solid #E2E8F0; background: #fff; color: #64748B; box-shadow: 0 4px 10px rgba(15, 42, 74, .06); } .tsf-arrow svg { width: 16px; height: 16px; } }
    .tsf-quick { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; }
    .tsf-q-lb { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #475569; }
    .tsf-q-lb svg { width: 15px; height: 15px; color: #64748B; }
    .tsf-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .tsf-chips button { height: 32px; padding: 0 14px; border: 1px solid #DCE6F2; border-radius: 999px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
    .tsf-chips button:hover { border-color: #93C5FD; color: #1D4ED8; background: #F5F9FF; }
    .tsf-chips button.on { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .25); }
    .tsf-sum { margin-left: auto; padding: 6px 12px; border-radius: 999px; background: #EEF4FF; font-size: 13px; font-weight: 700; color: #1D4ED8; }
    .tsf-sum.bad { background: #FEECEC; color: #DC2626; }
    .tsf-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding-top: 14px; border-top: 1px solid #EEF2F7; }
    .tsf-foot small { display: inline-flex; align-items: flex-start; line-height: 1.5; gap: 6px; font-size: 12px; color: #64748B; }
    .tsf-foot small svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
    .tsf-acts { display: flex; gap: 10px; }
    .tsf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 42px; padding: 0 20px; border-radius: 12px; border: 1px solid transparent; font: inherit; font-size: 14px; font-weight: 700; white-space: nowrap; cursor: pointer; transition: all .15s; }
    .tsf-btn svg { width: 16px; height: 16px; }
    .tsf-btn.ghost { border-color: #DCE6F2; background: #fff; color: #475569; }
    .tsf-btn.ghost:hover { background: #F8FAFC; }
    .tsf-btn.pri { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 8px 20px rgba(37, 99, 235, .28); }
    .tsf-btn.pri:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(37, 99, 235, .32); }
    .tsf-btn.pri:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }
    @media (max-width: 340px) { .tsf-dt { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 560px) { .tsf-sum { margin-left: 0; } .tsf-acts { width: 100%; } .tsf-btn { flex: 1; } }
</style>
<script>
    window.tsfForm = function (o) {
        const pad = (n) => String(n).padStart(2, '0');
        const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        const days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        const fresh = !o.sd;
        const base = new Date(); base.setMinutes(0, 0, 0); base.setHours(base.getHours() + 1);
        return {
            sd: o.sd || ymd(base), sh: o.sh || pad(base.getHours()), sm: o.sm || '00',
            ed: o.ed || '', eh: o.eh || '', em: o.em || '', lastDur: 90, tried: false,
            init() { if (fresh || !this.ed) this.setDur(90); this.lastDur = this.dur > 0 ? this.dur : 90; },
            at(d, h, m) { const t = new Date(d + 'T' + h + ':' + m + ':00'); return isNaN(t) ? null : t; },
            get dur() { const a = this.at(this.sd, this.sh, this.sm), b = this.at(this.ed, this.eh, this.em); return a && b ? Math.round((b - a) / 60000) : 0; },
            setDur(min) { const a = this.at(this.sd, this.sh, this.sm); if (!a) return; const b = new Date(a.getTime() + min * 60000); this.ed = ymd(b); this.eh = pad(b.getHours()); this.em = pad(b.getMinutes() - b.getMinutes() % 5); this.lastDur = min; this.tried = false; },
            onStart() { this.setDur(this.lastDur > 0 ? this.lastDur : 90); },
            durLabel(m) { const h = Math.floor(m / 60), r = m % 60; return h ? h + ' giờ' + (r ? ' ' + r + ' phút' : '') : r + ' phút'; },
            dayLabel(d) { const t = this.at(d, '12', '00'); return t ? days[t.getDay()] + ', ' + pad(t.getDate()) + '/' + pad(t.getMonth() + 1) : ''; },
        };
    };
</script>
@endonce

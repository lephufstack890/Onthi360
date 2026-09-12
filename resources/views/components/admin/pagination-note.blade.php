{{-- Dòng "Hiển thị x/y" dưới bảng.
     GIỮ NGUYÊN ghi chú cũ: 2 nút điều hướng vẫn là nút giả (disabled) vì controller đang trả
     mảng chứ chưa phải paginator của Eloquent — đây là việc của tầng dữ liệu, không phải UI,
     nên lần làm lại giao diện này không đụng vào. --}}
@props(['shown' => null, 'total' => null])
<div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-sky-100 bg-white px-4 py-3 text-[11px] text-slate-500 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
    <span>Hiển thị <strong class="font-bold text-slate-700">{{ $shown ?? '—' }}</strong> / {{ $total ?? '—' }} kết quả</span>
    <div class="flex items-center gap-1">
        <button type="button" disabled aria-label="Trang trước"
                class="grid h-8 w-8 place-items-center rounded-lg border border-sky-100 bg-white text-slate-400 disabled:cursor-not-allowed disabled:opacity-40">
            <x-lucide name="chevron-left" class="h-4 w-4" />
        </button>
        <button type="button" disabled aria-label="Trang sau"
                class="grid h-8 w-8 place-items-center rounded-lg border border-sky-100 bg-white text-slate-400 disabled:cursor-not-allowed disabled:opacity-40">
            <x-lucide name="chevron-right" class="h-4 w-4" />
        </button>
    </div>
</div>

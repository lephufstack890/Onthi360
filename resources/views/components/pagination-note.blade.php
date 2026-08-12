{{-- TODO: thay bằng $paginator->links() thật khi controller trả Eloquent paginate(). --}}
<div class="flex items-center justify-between text-sm text-slate-500 mt-4">
    <span>Hiển thị {{ $shown ?? '—' }} / {{ $total ?? '—' }} kết quả</span>
    <div class="flex gap-1">
        <button type="button" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-400" disabled>‹ Trước</button>
        <button type="button" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-400" disabled>Sau ›</button>
    </div>
</div>

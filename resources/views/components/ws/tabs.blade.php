{{-- Dải chuyển tab — đổi từ kiểu gạch chân hồng sang viên bo tròn nền xanh như bản mẫu. --}}
@props(['tabs' => []])
<div class="flex items-center gap-1.5 overflow-x-auto rounded-2xl border border-sky-100 bg-white p-1.5 shadow-[0_2px_8px_rgba(0,90,180,.04)] no-scrollbar">
    @foreach ($tabs as $tab)
        @php $isActive = $tab['active'] ?? false; @endphp
        <a href="{{ $tab['href'] }}"
           @if ($isActive) aria-current="page" @endif
           class="inline-flex min-h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-1.5 text-[11px] font-bold transition-all {{ $isActive ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'text-slate-600 hover:bg-sky-50 hover:text-blue-700' }}">
            {{ $tab['label'] }}
            @isset($tab['count'])
                <span class="rounded-md px-1.5 py-0.5 text-[9px] font-bold {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $tab['count'] }}</span>
            @endisset
        </a>
    @endforeach
</div>

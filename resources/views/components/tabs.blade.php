@props(['tabs' => []])
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-6 overflow-x-auto">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['href'] }}"
               class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 {{ ($tab['active'] ?? false) ? 'border-rose-600 text-rose-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ $tab['label'] }}
                @if(isset($tab['count']))
                    <span class="ml-1 text-xs text-slate-400">({{ $tab['count'] }})</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>

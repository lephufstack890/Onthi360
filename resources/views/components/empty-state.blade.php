@props(['title' => 'Chưa có dữ liệu', 'description' => null, 'actionLabel' => null, 'actionHref' => null])
<div class="text-center py-16 rounded-2xl bg-white border border-dashed border-slate-200">
    <div class="text-4xl mb-3">🗒️</div>
    <h3 class="font-medium text-slate-700">{{ $title }}</h3>
    @if($description)
        <p class="text-sm text-slate-500 mt-1">{{ $description }}</p>
    @endif
    @if($actionLabel)
        <a href="{{ $actionHref ?? '#' }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">
            {{ $actionLabel }}
        </a>
    @endif
</div>

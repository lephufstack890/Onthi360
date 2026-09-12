{{-- Thẻ trắng dùng chung của trang quản trị — đúng vỏ thẻ của source
     (bg-white rounded-3xl border border-sky-100 p-5). Có thể kèm tiêu đề + icon + 1 liên kết
     hành động ở góc phải, giống <CardTitle> trong RoleWorkspace.jsx. --}}
@props([
    'title' => null,
    'icon' => null,
    'action' => null,
    'actionHref' => null,
    'padding' => 'p-4 sm:p-5',
])
<section {{ $attributes->merge(['class' => 'rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] '.$padding]) }}>
    @if ($title)
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                @if ($icon)
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600">
                        <x-lucide :name="$icon" class="h-3.5 w-3.5" />
                    </span>
                @endif
                <h2 class="min-w-0 truncate text-sm font-bold text-slate-800">{{ $title }}</h2>
            </div>
            @if ($action && $actionHref)
                <a href="{{ $actionHref }}" class="inline-flex shrink-0 items-center gap-0.5 text-xs font-bold text-blue-600 hover:underline">
                    {{ $action }}<x-lucide name="chevron-right" class="h-3 w-3" />
                </a>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>

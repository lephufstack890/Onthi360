{{-- Ô "chưa có dữ liệu" — viền đứt như các màn công khai, icon lucide thay emoji. --}}
@props([
    'title' => 'Chưa có dữ liệu',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'icon' => 'inbox',
])
<div class="rounded-3xl border border-dashed border-sky-200 bg-white p-10 text-center">
    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-sky-50 text-sky-600">
        <x-lucide :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="mt-3 text-sm font-bold text-slate-800">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
    @endif
    @if ($actionLabel)
        <x-admin.btn :href="$actionHref ?? '#'" variant="primary" class="mt-4">{{ $actionLabel }}</x-admin.btn>
    @endif
</div>

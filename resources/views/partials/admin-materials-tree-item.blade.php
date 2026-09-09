@php
    $statusLabels = [
        'draft' => ['label' => 'Bản nháp', 'tone' => 'neutral'],
        'pending_review' => ['label' => 'Chờ duyệt', 'tone' => 'warning'],
        'published' => ['label' => 'Xuất bản', 'tone' => 'success'],
        'archived' => ['label' => 'Lưu trữ', 'tone' => 'neutral'],
    ];
    $statusMeta = $statusLabels[$item['statusValue']] ?? ['label' => $item['statusValue'], 'tone' => 'neutral'];
    $children = $item['children'] ?? [];
    $hasChildren = count($children) > 0;
@endphp
<div @if ($depth > 0) style="padding-left: {{ $depth * 1.5 }}rem" @endif>
    <div class="flex items-center gap-3 {{ $depth === 0 ? 'py-3' : 'py-2' }}">
        <span class="shrink-0">{{ $depth === 0 ? '📄' : '↳' }}</span>
        <p class="text-sm flex-1 {{ $depth === 0 ? 'font-medium text-slate-700' : 'text-slate-600' }}">{{ $item['title'] }}</p>
        <x-status-badge :tone="$statusMeta['tone']">{{ $statusMeta['label'] }}</x-status-badge>
        <a href="{{ route('admin.content.show', $item['id']) }}" class="text-sm text-rose-600 font-medium shrink-0">Xem</a>
        <form method="POST" action="{{ route('admin.content.materials.destroy', $item['id']) }}" class="inline shrink-0" onsubmit="return confirm('Xoá vĩnh viễn học liệu này cùng toàn bộ bài con và file PDF liên quan? Không thể khôi phục.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-rose-500 hover:text-rose-700 font-medium">Xoá</button>
        </form>
    </div>

    @if ($hasChildren)
        <div class="divide-y divide-slate-100">
            @foreach ($children as $child)
                @include('partials.admin-materials-tree-item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>

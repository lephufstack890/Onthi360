{{--
    SỬA 25/8 (8 — "mục lục đa cấp"): partial đệ quy hiển thị 1 dòng Mục lục ở public
    materials.show — TỰ GỌI LẠI CHÍNH NÓ cho từng $item['children'] nên bao nhiêu cấp lồng
    nhau (chương chứa bài, bài chứa mục con...) cũng hiển thị đúng, không giới hạn cứng 2 cấp.
    Blade @include đệ quy chỉ là biên dịch template — tự dừng khi hết dữ liệu con thật, không
    lặp vô hạn.

    Biến truyền vào:
    - $item: ['id','title','hasContent','children'] — xem MaterialService::buildTocTree().
    - $index: vị trí trong danh sách anh em cùng cấp (dùng đánh số ở cấp 1).
    - $depth: 0 = cấp 1 (chương/mục gốc, có số thứ tự tròn), >0 = cấp con (thụt lề + chấm nhỏ).
    - $owned: đã mua quyền học tài liệu này chưa (truyền nguyên từ show.blade.php).
--}}
@php
    $readable = $owned && ($item['hasContent'] ?? false);
    $children = $item['children'] ?? [];
    $hasChildren = count($children) > 0;
    // SỬA 27/8 ("giáo viên đọc tài liệu bị 403"): TRƯỚC ĐÂY link luôn trỏ cứng sang
    // student.materials.read — chỉ role student mở được (xem routes/web.php), giáo viên bấm
    // vào đây (sau khi mua) là dính 403. $owned=true chỉ khi đã đăng nhập nên auth()->user()
    // luôn có ở nhánh này.
    $readRouteName = auth()->user()?->hasAnyRole(\App\Models\Role::TEACHER)
        ? 'teacher.materials.read'
        : 'student.materials.read';
@endphp
<div @if ($hasChildren) x-data="{ open: false }" @endif @if ($depth > 0) style="padding-left: {{ $depth * 1.5 }}rem" @endif>
    <div class="flex items-center gap-3 {{ $depth === 0 ? 'py-3' : 'py-2' }}">
        @if ($depth === 0)
            <span class="w-7 h-7 rounded-full bg-rose-50 text-rose-600 text-xs font-semibold flex items-center justify-center shrink-0">{{ $index + 1 }}</span>
        @else
            <span class="shrink-0" style="display:inline-block;width:6px;height:6px;border-radius:50%;background-color:#cbd5e1"></span>
        @endif
        @if ($readable)
            <a href="{{ route($readRouteName, $item['id']) }}" class="text-sm {{ $depth === 0 ? 'font-medium text-slate-700' : 'text-slate-600' }} hover:text-rose-600">{{ $item['title'] }}</a>
        @else
            <p class="text-sm flex items-center gap-1 {{ $depth === 0 ? 'font-medium text-slate-500' : 'text-slate-400' }}">
                {{ $item['title'] }}
                @if ($item['hasContent'] ?? false)
                    <span class="text-slate-300" title="Cần mua quyền để đọc">🔒</span>
                @endif
            </p>
        @endif

        @if ($hasChildren)
            {{-- SỬA 25/8 (9 — "mục lục dạng dropdown"): mặc định ĐÓNG (open=false), bấm vào mũi
                 tên mới xổ ra — tách RIÊNG khỏi tiêu đề/link phía trên để bấm vào tên bài vẫn
                 vào đọc bình thường như cũ, không bị nút đóng/mở "nuốt" mất click. --}}
            <button type="button" @click="open = !open" style="margin-left:auto" class="shrink-0 p-1 text-slate-400 hover:text-rose-600" :aria-expanded="open.toString()" aria-label="Đóng/mở danh sách con">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform" :style="open ? 'transform: rotate(180deg)' : ''">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <div x-show="open" x-cloak class="divide-y divide-slate-100">
            @foreach ($children as $childIndex => $child)
                @include('partials.materials-toc-item', ['item' => $child, 'index' => $childIndex, 'depth' => $depth + 1, 'owned' => $owned])
            @endforeach
        </div>
    @endif
</div>

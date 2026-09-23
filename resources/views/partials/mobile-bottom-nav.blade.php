{{--
  Thanh điều hướng dưới đáy, chỉ hiện trên điện thoại (lg:hidden) ở vỏ trang công khai.

  SỬA 23/9 (khách: "icon dùng emoji cẩu thả, dùng SVG cho cùng phong cách với trang chủ") —
  4 emoji 🏠 📚 📝 ⋯ thay bằng <x-lucide> (SVG) đúng bộ icon mà thanh điều hướng trên cùng và
  các trang công khai đang dùng. Thêm trạng thái ĐANG Ở TRANG NÀO (trước đây 3 mục nhìn y hệt
  nhau, không biết mình đang đứng đâu) và chừa đệm dưới cho vùng vuốt của iPhone.

  Danh sách mục và route giữ NGUYÊN, chỉ thay mục "Thêm" (trước là nút bấm không làm gì) bằng
  Bảng xếp hạng — một trang công khai có thật, hợp với 3 mục còn lại.
--}}
@php
    $bottomNavItems = [
        ['label' => 'Trang chủ', 'icon' => 'home', 'route' => 'home'],
        ['label' => 'Khóa học', 'icon' => 'book-open', 'route' => 'courses.index'],
        ['label' => 'Luyện tập', 'icon' => 'notebook-pen', 'route' => 'practice.index'],
        ['label' => 'Xếp hạng', 'icon' => 'bar-chart-3', 'route' => 'leaderboard.index'],
    ];
@endphp

<nav aria-label="Điều hướng nhanh"
     class="fixed inset-x-0 bottom-0 z-30 border-t border-sky-100 bg-white/95 pb-2 shadow-sm backdrop-blur-md lg:hidden">
    <div class="flex items-stretch justify-around">
        @foreach ($bottomNavItems as $item)
            @php($isActive = request()->routeIs($item['route']))
            <a href="{{ route($item['route']) }}"
               @if ($isActive) aria-current="page" @endif
               @class([
                   'flex min-h-12 flex-1 flex-col items-center justify-center gap-1 px-1 py-1.5 text-[10px] font-bold transition-colors',
                   'text-[#126F91]' => $isActive,
                   'text-slate-500 hover:text-[#126F91]' => ! $isActive,
               ])>
                <span @class([
                    'grid h-8 w-8 place-items-center rounded-xl transition-colors',
                    'bg-[#EAF5F8]' => $isActive,
                ])>
                    <x-lucide :name="$item['icon']" class="h-4.5 w-4.5" />
                </span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>

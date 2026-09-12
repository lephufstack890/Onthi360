{{-- Một ô nhập trong biểu mẫu: nhãn + nội dung + gợi ý + lỗi.

     Cách dùng: bọc x-ws.field quanh thẻ input/textarea/x-ws.select của bạn, truyền
     label và name (name dùng để tự in lỗi kiểm tra dữ liệu của đúng trường đó).

     Lớp CSS chuẩn cho thẻ input/textarea đặt ở .admin-input (resources/css/app.css) để các
     view chỉ cần gắn class="admin-input", không phải chép một chuỗi class dài ở 50+ màn. --}}
@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="mb-1.5 block text-[11px] font-bold text-slate-700">
            {{ $label }}@if ($required)<span class="ml-0.5 text-rose-500">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1 text-[10px] leading-relaxed text-slate-400">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="mt-1 flex items-start gap-1 text-[10px] font-medium leading-4 text-rose-600">
                <x-lucide name="info" class="mt-px h-3 w-3 shrink-0" />{{ $message }}
            </p>
        @enderror
    @endif
</div>

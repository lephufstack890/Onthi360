{{-- Bảng dữ liệu — cùng ngôn ngữ với bảng trong source: chữ nhỏ, đầu bảng chữ hoa xám nhạt,
     mỗi dòng ngăn bằng đường kẻ mảnh, có sọc chẵn/lẻ cho dễ dò mắt.
     Dùng y như x-data-table cũ: <x-admin.table :columns="[...]"> <tr>...</tr> </x-admin.table> --}}
@props(['columns' => [], 'minWidth' => 'min-w-[720px]'])
<div class="overflow-hidden rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)]">
    <div class="overflow-x-auto">
        <table class="w-full {{ $minWidth }} text-left text-xs">
            <thead class="bg-[#F8FBFE] text-[10px] font-bold uppercase tracking-[0.06em] text-slate-500">
                <tr>
                    @foreach ($columns as $col)
                        <th class="whitespace-nowrap px-4 py-3 font-bold">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-sky-50/60 [&>tr]:transition-colors">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>

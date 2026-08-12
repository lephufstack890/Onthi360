{{--
  Khung bảng dùng chung cho các trang danh sách admin.
  $columns: ['Tên', 'Email', 'Vai trò', 'Trạng thái', ''] (cột cuối rỗng = hành động)
  $rows: slot chứa các <tr> — truyền từ view gọi.
--}}
@props(['columns' => []])
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                @foreach ($columns as $col)
                    <th class="px-4 py-3 font-medium">{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            {{ $slot }}
        </tbody>
    </table>
</div>

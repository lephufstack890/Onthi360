{{--
  SỬA 18/9 (khách: "trong admin thêm tính năng thêm mã kích hoạt. Khi thêm mã kích hoạt xong thì
  đưa cho tài khoản học sinh hoặc giáo viên để mở. Lưu ý mã kích hoạt này thuộc tài liệu nào và
  thuộc tài khoản nào thì tài khoản đó mở được thôi").

  Biểu mẫu dựng theo đúng khuôn màn "Cấp quyền truy cập" (admin/access-rights/create.blade.php)
  để 2 màn cùng họ. Khác nhau ở chỗ: màn kia cấp QUYỀN ngay lập tức, màn này chỉ cấp MÃ — quyền
  chỉ có khi chính tài khoản được cấp nhập mã và bấm Kích hoạt (7.4: thời hạn tính từ lúc đó).
--}}
@extends('layouts.admin')

@section('title', 'Cấp mã kích hoạt')
@section('page-title', 'Cấp mã kích hoạt')

@section('content')
    @php
        $products = $products ?? [];
        $users = $users ?? [];
        $scopes = $scopes ?? [];
    @endphp

    <a href="{{ route('admin.activation-codes.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Mã kích hoạt</a>

    <x-ws.page-header title="Cấp mã kích hoạt" icon="ticket"
                      subtitle="Mã được khoá theo đúng 1 tài liệu và đúng 1 tài khoản — tài khoản khác nhập vào sẽ không mở được." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('admin.activation-codes.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="user_id">Cấp cho tài khoản</label>
                <x-ws.select id="user_id" name="user_id" required>
                    <option value="">— Chọn tài khoản học sinh hoặc giáo viên —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u['id'] }}" @selected((string) old('user_id') === (string) $u['id'])>
                            {{ $u['roleLabel'] }} · {{ $u['name'] }} ({{ $u['email'] }})
                        </option>
                    @endforeach
                </x-ws.select>
                <p class="text-xs text-slate-400 mt-1">Chỉ đúng tài khoản này mới kích hoạt được mã. Người khác nhập mã sẽ bị từ chối.</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="product_id">Tài liệu được mở</label>
                <x-ws.select id="product_id" name="product_id" required>
                    <option value="">— Chọn tài liệu —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)>
                            {{ $p->title }} ({{ $p->duration_months ? $p->duration_months.' tháng' : 'không giới hạn' }})
                        </option>
                    @endforeach
                </x-ws.select>
                <p class="text-xs text-slate-400 mt-1">Mã chỉ mở đúng tài liệu này — không dùng chéo sang tài liệu khác được.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="scope">Phạm vi quyền</label>
                    <x-ws.select id="scope" name="scope" required>
                        @foreach ($scopes as $value => $label)
                            <option value="{{ $value }}" @selected(old('scope', 'personal_learning') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                    <p class="text-xs text-slate-400 mt-1">Chọn "Dùng để dạy" thì tài khoản phải là giáo viên đã được duyệt (7.2).</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="validity_months">Thời hạn (tháng)</label>
                    <input id="validity_months" name="validity_months" type="number" min="1" max="120"
                           value="{{ old('validity_months') }}" placeholder="Theo tài liệu" class="admin-input">
                    <p class="text-xs text-slate-400 mt-1">Để trống = dùng thời hạn của tài liệu; tài liệu cũng không đặt thì quyền vĩnh viễn.</p>
                </div>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="note">Ghi chú (không bắt buộc)</label>
                <textarea id="note" name="note" rows="2" maxlength="500"
                          placeholder="Ví dụ: Cấp bù cho học sinh mua sách bản in tại nhà sách..."
                          class="admin-input">{{ old('note') }}</textarea>
            </div>

            <div class="rounded-xl bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                Cấp mã <span class="font-medium">chưa phải là đã có quyền</span> — thời hạn chỉ bắt đầu chạy khi chính tài khoản
                được cấp nhập mã ở trang "Kích hoạt mã". Muốn mở quyền ngay không qua mã thì dùng
                <a href="{{ route('admin.access-rights.create') }}" class="font-medium underline">Cấp quyền truy cập</a>.
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Cấp mã</button>
                <a href="{{ route('admin.activation-codes.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Cấp quyền truy cập')
@section('page-title', 'Cấp quyền truy cập')

@section('content')
    @php $products = $products ?? []; $scopes = $scopes ?? []; $users = $users ?? []; @endphp

    <a href="{{ route('admin.access-rights.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Quyền truy cập</a>

    <x-ws.page-header title="Cấp quyền truy cập" icon="shield-check" subtitle="Cấp trực tiếp — KHÁC với luồng đơn hàng. Chỉ dùng khi có lý do rõ ràng (hỗ trợ, đền bù, tặng...)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('admin.access-rights.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="user_id">Người dùng</label>
                <x-ws.select id="user_id" name="user_id" required>
                    <option value="">— Chọn người dùng —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </x-ws.select>
                <p class="text-xs text-slate-400 mt-1">Nếu cấp "Dùng để dạy", người dùng phải là giáo viên đã được Admin duyệt (3.3, 7.2).</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="product_id">Tài liệu</label>
                <x-ws.select id="product_id" name="product_id" required>
                    <option value="">— Chọn tài liệu —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)>
                            {{ $p->title }} ({{ $p->duration_months ? $p->duration_months.' tháng' : 'không giới hạn' }})
                        </option>
                    @endforeach
                </x-ws.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="scope">Phạm vi quyền</label>
                    <x-ws.select id="scope" name="scope" required>
                        @foreach ($scopes as $value => $label)
                            <option value="{{ $value }}" @selected(old('scope', 'personal_learning') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="expires_at">Hết hạn vào ngày</label>
                    <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}"
                           class="admin-input">
                    <p class="text-xs text-slate-400 mt-1">Để trống = dùng thời hạn mặc định của tài liệu (hoặc không giới hạn nếu tài liệu cũng không đặt).</p>
                </div>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="reason">Lý do cấp quyền (bắt buộc, 10.4)</label>
                <textarea id="reason" name="reason" rows="3" required maxlength="1000"
                          placeholder="Ví dụ: Hỗ trợ học sinh theo chương trình học bổng..."
                          class="admin-input">{{ old('reason') }}</textarea>
            </div>

            <div class="rounded-xl bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                Quyền có hiệu lực <span class="font-medium">ngay khi cấp</span> — thời hạn tính từ thời điểm này, không phải lúc tạo đơn
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Cấp quyền</button>
                <a href="{{ route('admin.access-rights.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

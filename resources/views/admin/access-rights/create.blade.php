@extends('layouts.admin')

@section('title', 'Cấp quyền truy cập')
@section('page-title', 'Cấp quyền truy cập')

@section('content')
    @php $products = $products ?? []; $scopes = $scopes ?? []; $users = $users ?? []; @endphp

    <a href="{{ route('admin.access-rights.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Quyền truy cập</a>

    <x-page-header title="🔐 Cấp quyền truy cập" subtitle="Cấp trực tiếp — KHÁC với luồng đơn hàng. Chỉ dùng khi có lý do rõ ràng (hỗ trợ, đền bù, tặng...)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <form method="POST" action="{{ route('admin.access-rights.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="user_id">Người dùng</label>
                <x-select id="user_id" name="user_id" required>
                    <option value="">— Chọn người dùng —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </x-select>
                <p class="text-xs text-slate-400 mt-1">Nếu cấp "Dùng để dạy", người dùng phải là giáo viên đã được Admin duyệt (3.3, 7.2).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="product_id">Tài liệu</label>
                <x-select id="product_id" name="product_id" required>
                    <option value="">— Chọn tài liệu —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)>
                            {{ $p->title }} ({{ $p->duration_months ? $p->duration_months.' tháng' : 'không giới hạn' }})
                        </option>
                    @endforeach
                </x-select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="scope">Phạm vi quyền</label>
                    <x-select id="scope" name="scope" required>
                        @foreach ($scopes as $value => $label)
                            <option value="{{ $value }}" @selected(old('scope', 'personal_learning') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="expires_at">Hết hạn vào ngày</label>
                    <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    <p class="text-xs text-slate-400 mt-1">Để trống = dùng thời hạn mặc định của tài liệu (hoặc không giới hạn nếu tài liệu cũng không đặt).</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="reason">Lý do cấp quyền (bắt buộc, 10.4)</label>
                <textarea id="reason" name="reason" rows="3" required maxlength="1000"
                          placeholder="Ví dụ: Hỗ trợ học sinh theo chương trình học bổng..."
                          class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('reason') }}</textarea>
            </div>

            <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                Quyền có hiệu lực <span class="font-medium">ngay khi cấp</span> — thời hạn tính từ thời điểm này, không phải lúc tạo đơn
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Cấp quyền</button>
                <a href="{{ route('admin.access-rights.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

{{--
  Route: access.activate | Frame: ACC-02
  Spec: 7.4 (thời hạn bắt đầu khi kích hoạt mã hợp lệ; mã sai scope không
  tự chuyển đổi).
  TODO controller: submit qua App\Services\OrderActivationService::activate().
--}}
@extends('layouts.guest')

@section('title', 'Kích hoạt mã')

@section('content')
    <div class="max-w-md mx-auto px-4 py-16">
        <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
            <div class="text-4xl mb-3">🔑</div>
            <h1 class="text-lg font-semibold text-slate-800 mb-1">Kích hoạt mã</h1>
            <p class="text-sm text-slate-500 mb-6">Thời hạn quyền bắt đầu tính từ lúc kích hoạt — không phải lúc đặt đơn (7.4).</p>

            <form class="space-y-4 text-left">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Mã kích hoạt</label>
                    <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-3 font-mono text-center tracking-widest" placeholder="OT360-XXXX-XXXX">
                </div>
                <button type="submit" class="w-full px-5 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Kích hoạt</button>
            </form>

            {{-- Ví dụ trạng thái lỗi — TODO: hiển thị đúng theo phản hồi server --}}
            <div class="mt-4 rounded-lg bg-rose-50 border border-rose-100 p-3 text-xs text-rose-700 text-left">
                Mã này thuộc phạm vi "Dùng để dạy" — không thể kích hoạt thành quyền học cá nhân (7.4).
            </div>
        </div>
    </div>
@endsection

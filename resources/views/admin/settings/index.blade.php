{{--
  Route: admin.settings.index
  Spec: 3.1 (Super Admin: cấu hình role, chính sách, tích hợp).
  TODO: chỉ Super Admin mới thấy trang này — kiểm tra thêm Policy/middleware riêng.
--}}
@extends('layouts.admin')

@section('title', 'Cấu hình hệ thống')
@section('page-title', 'Cấu hình')

@section('content')
    <x-page-header title="Cấu hình hệ thống" subtitle="Chỉ Super Admin có toàn quyền cấu hình role, chính sách và tích hợp (3.1)." />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-2">Vai trò & quyền</h2>
            <p class="text-sm text-slate-500">TODO: quản lý danh sách role, ma trận quyền (3.2).</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-2">Tích hợp thanh toán</h2>
            <p class="text-sm text-slate-500">TODO: bật/tắt VNPAY, cấu hình thanh toán ngoài hệ thống (7.4).</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-2">OCR / nhập đề</h2>
            <p class="text-sm text-slate-500">TODO: chọn engine OCR, ngưỡng gắn cờ nhận dạng kém (18 mục 7).</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-2">Chính sách đánh giá</h2>
            <p class="text-sm text-slate-500">TODO: ngưỡng 5 review, thời hạn sửa review 7 ngày (9.2, 9.5).</p>
        </div>
    </div>
@endsection

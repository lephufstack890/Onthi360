{{--
  Route: info.index | Frame: PUB-11
  Spec: 4.1 (Giới thiệu, hướng dẫn, tin tức, chính sách, liên hệ, FAQ).
  TODO: tách route riêng cho từng mục nếu nội dung dài (info.about/info.faq/info.contact...).
--}}
@extends('layouts.guest')

@section('title', 'Thông tin')

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-8">
        <x-page-header title="Thông tin" subtitle="Giới thiệu, hướng dẫn sử dụng, chính sách và liên hệ." />

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="font-medium text-slate-700 mb-2">Giới thiệu</h2>
            <p class="text-sm text-slate-500">TODO: nội dung giới thiệu Ôn Thi 360 (1.1-1.2 BA spec).</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="font-medium text-slate-700 mb-2">Hướng dẫn sử dụng</h2>
            <p class="text-sm text-slate-500">TODO: hướng dẫn nhanh cho học sinh/giáo viên/phụ huynh.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="font-medium text-slate-700 mb-2">Chính sách</h2>
            <p class="text-sm text-slate-500">TODO: chính sách bảo mật, điều khoản sử dụng, chính sách hoàn tiền (7.4).</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="font-medium text-slate-700 mb-2">Liên hệ</h2>
            <p class="text-sm text-slate-500">TODO: form liên hệ / thông tin liên hệ chính thức.</p>
        </div>
    </div>
@endsection

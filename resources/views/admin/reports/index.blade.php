{{--
  Route: admin.reports.index
  Spec: 2.3 (P1: báo cáo thương mại sâu) — P0 chỉ cần báo cáo vận hành cơ bản.
  TODO controller: truyền số liệu tổng hợp thật (đơn hàng, quyền, hoạt động học).
--}}
@extends('layouts.admin')

@section('title', 'Báo cáo')
@section('page-title', 'Báo cáo')

@section('content')
    <x-page-header title="📄 Báo cáo" subtitle="Báo cáo vận hành cơ bản cho P0; báo cáo thương mại sâu thuộc P1 (2.3)." />

    <x-empty-state
        title="Chưa có báo cáo nào được cấu hình"
        description="TODO: thêm báo cáo doanh thu theo sản phẩm, tỷ lệ hoàn thành theo lớp, hiệu suất OCR." />
@endsection

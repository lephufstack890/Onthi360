@extends('layouts.guest')

@section('title', 'Kho bài tập Thuật toán & Lập trình')
@section('meta-description', 'Kho luyện tập Tin học của Ôn Thi 360 — bài tập theo chuyên đề có chấm tự động và đề thi luyện tập mô phỏng, lọc theo chuyên đề, độ khó và loại đề.')

@section('content')
{{-- ═══════════════ [PRACTICE] MÀN LUYỆN TẬP ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/PracticePage.jsx (PRACTICE-01 … PRACTICE-07).
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\PracticeService::indexData):
       · bảng bài tập <- $problems  (câu hỏi thật: mã, chuyên đề, độ khó, tỷ lệ AC tính từ
                         attempt_answers, trạng thái của chính người đang xem)
       · chuyên đề    <- $practiceTags  (App\Support\PracticeFilters — cùng nguồn với màn học sinh)
       · dạng câu     <- $practiceTypes
       · thẻ đề thi   <- $items     (đề luyện tập đã phát hành)
     Bản mẫu có vài con số minh hoạ không có nguồn dữ liệu (chuỗi ngày luyện tập, điểm cao
     nhất) — thay bằng số liệu thật tương ứng. --}}
<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
@include('partials.practice-catalog')
</div>
@endsection

{{-- Script của kho luyện tập đã nằm trong chính partials/practice-catalog (18/9) — để thêm
     ở đây nữa là hàm onthiPracticePage bị khai báo hai lần. --}}

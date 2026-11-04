@extends('layouts.guest')

{{-- SỬA 4/11 (SEO "chấm code online") --}}
@section('title', 'Luyện tập Thuật toán & Lập trình — Chấm code online C++, Python')
@section('meta-description', 'Chấm code online C++, Python tự động, có kết quả ngay: kho bài tập Tin học theo chuyên đề và đề thi luyện tập mô phỏng, lọc theo chuyên đề, độ khó và loại đề.')
@section('meta-keywords', 'chấm code online, chấm bài lập trình online, online judge, luyện code C++, luyện code Python, bài tập thuật toán, luyện thi HSG Tin học, đề thi Tin học')

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
@include('partials.practice-catalog', ['showProblemProvinceAuthor' => false, 'showMyHistoryButton' => false])
</div>
@endsection

{{-- Script của kho luyện tập đã nằm trong chính partials/practice-catalog (18/9) — để thêm
     ở đây nữa là hàm onthiPracticePage bị khai báo hai lần. --}}

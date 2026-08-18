{{--
  Route: student.assessment.take (nhánh chặn) | Frame: STU-05
  Spec: yêu cầu ngày 18/8 — "khi bấm vào thi thì thông báo hết lượt thay vì báo lỗi này":
  trước đây App\Http\Controllers\Student\AssessmentController::take() dùng abort(422, ...)
  khi App\Services\Student\AssessmentService::buildTakeData() ném ValidationException (hết
  lượt làm lại theo resubmission_policy — 6.3, bài giao chưa mở/chưa tới ca thi riêng của học
  sinh này, hoặc chưa đủ quyền học liệu — 7.1/7.3) — abort() bay thẳng ra trang lỗi kỹ thuật
  của Laravel (tiếng Anh, có stack trace nếu APP_DEBUG=true), dù đây là thông báo NGHIỆP VỤ
  bình thường học sinh cần đọc được, không phải lỗi hệ thống. Trang này thay thế abort() đó —
  hiện đúng thông điệp ($message, tiếng Việt, lấy nguyên văn từ ValidationException) trong
  giao diện quen thuộc của học sinh (cùng layout/sidebar mọi trang khác), kèm 2 lối ra rõ
  ràng thay vì màn hình chết.
--}}
@extends('layouts.student')

@section('title', 'Không thể vào thi')
@section('page-title', 'Không thể vào thi')

@section('content')
    <div class="max-w-lg mx-auto text-center bg-white rounded-2xl border border-slate-200 p-8 lg:p-10">
        <div class="text-4xl mb-3">🚫</div>
        <h1 class="text-lg font-semibold text-slate-700 mb-2">Chưa thể vào làm bài lúc này</h1>
        <p class="text-sm text-slate-500 leading-relaxed">{{ $message }}</p>

        <div class="flex flex-wrap justify-center gap-3 mt-6">
            <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                Về trang của tôi
            </a>
            <a href="{{ route('student.practice.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
                Luyện tập đề khác
            </a>
        </div>
    </div>
@endsection

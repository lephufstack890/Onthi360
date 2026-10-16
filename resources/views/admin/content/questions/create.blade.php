@extends('layouts.admin')

@section('title', 'Tạo câu hỏi')
@section('page-title', 'Tạo câu hỏi (Kho chung)')

@section('content')
    {{-- SỬA 16/10 (khách: "chỗ thêm câu hỏi của Kho bài tập / câu hỏi … admin và giáo viên cũng vậy" — giống trang tài liệu) —
         BỎ ô "Loại câu hỏi" + toàn bộ form nhập tay ở màn TẠO. Giờ chỉ còn 1 khối tải gói ZIP theo mẫu OT360-QPACK, hệ thống
         tự nhận dạng theo content.type (partials/question-zip-upload). Nhập xong ContentController::questionsZipImportStore()
         chuyển sang admin.content.questions.edit — form Sửa vẫn nhập/sửa tay đầy đủ (đề, đáp án, test, tag, môn, tỉnh, năm…).
         Route admin.content.questions.store vẫn giữ nguyên ở backend (không còn form nào gọi tới từ màn này). --}}
    @include('admin.content.questions._editor-style')

    <div class="qe">
        <a href="{{ route('admin.content.index', ['tab' => 'questions']) }}" class="qe-back">‹ Quay lại Nội dung</a>

        <div class="qe-hero-gap">
            <x-ws.page-header title="Thêm câu hỏi" icon="circle-help" eyebrow="Kho chung · Câu hỏi" subtitle="Tải 1 gói ZIP theo mẫu — hệ thống tự nhận Trắc nghiệm, Đúng / Sai, Điền từ hay Lập trình." />
        </div>

        @include('partials.question-zip-upload', [
            'action' => route('admin.content.questions.zipImport'),
            'maxKb' => \App\Services\Admin\ContentService::maxQuestionZipKb(),
            'reviewLabel' => 'trang Sửa câu hỏi (Kho chung)',
        ])
    </div>
@endsection

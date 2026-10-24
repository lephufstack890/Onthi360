{{-- SỬA 24/10 — hộp thoại vào thi (source: EntryDialog). Khảo sát: nhập mật khẩu. Cuộc thi: xác nhận + mã đề.
     Gửi student.competitions.enter → ContestEntryService::enter() kiểm lại toàn bộ ở máy chủ. --}}
@php
    $choiceMode = ! $survey && $round['assignmentMode'] === 'choice' && count($forms) > 1 && $assignedForm === null;
@endphp
<dialog x-ref="entryDialog" class="contest-entry-dialog contest-ui" aria-labelledby="contest-entry-title" @click="if ($event.target === $refs.entryDialog) closeEntry()">
    <form method="POST" action="{{ $round['enterUrl'] }}">
        @csrf
        <button type="button" class="contest-dialog-close" aria-label="Đóng hộp thoại tham gia" @click="closeEntry()"><x-lucide name="x" /></button>
        <span class="contest-icon {{ $survey ? 'teal' : 'amber' }}"><x-lucide :name="$survey ? 'key-round' : 'trophy'" /></span>
        <h2 id="contest-entry-title">{{ $survey ? 'Nhập mật khẩu khảo sát' : ($resume ? 'Tiếp tục bài đang làm' : 'Sẵn sàng vào thi?') }}</h2>
        <p>{{ $round['label'] }}</p>
        <div class="contest-dialog-rules">
            <span><x-lucide name="clock-3" />{{ $round['duration'] }} / lượt</span>
            <span><x-lucide name="file-text" />{{ $round['questionCount'] }} câu hỏi</span>
            <span><x-lucide :name="$survey ? 'rotate-ccw' : 'shield-check'" />{{ $survey ? 'Có thể làm nhiều lần' : 'Chỉ nộp và chấm 1 lần' }}</span>
        </div>
        @if (! $survey)
            @if ($assignedFormLabel)
                <p class="contest-dialog-help"><b>{{ $assignedFormLabel }}</b> · Đề được giữ cố định từ khi bắt đầu.</p>
            @elseif ($choiceMode)
                <input type="hidden" name="form" :value="selectedForm">
                <p class="contest-dialog-help"><b x-text="selectedForm ? 'Đề ' + (100 + Number(selectedForm)) : 'Chưa chọn đề'"></b> · Chọn mã đề ở mục "Danh sách đề thi". Lựa chọn được chốt khi bắt đầu.</p>
            @elseif (count($forms) > 1)
                <p class="contest-dialog-help"><b>Giao đề ngẫu nhiên</b> · Hệ thống giao một đề tương đương khi bạn xác nhận; không đổi đề khi tải lại.</p>
            @endif
        @endif
        @if ($survey && count($paperList ?? []) > 1)
            {{-- SỬA 24/10 — đề học sinh đã chọn ở "Danh sách đề". --}}
            <input type="hidden" name="paper" :value="selectedPaper">
            <p class="contest-dialog-help"><b x-text="paperLabels[selectedPaper] || 'Chưa chọn đề'"></b> · {{ ($doingPaperId ?? null) ? 'Tiếp tục đúng đề đang làm dở.' : 'Đổi đề ở mục "Danh sách đề" trong tab Thông tin kỳ thi.' }}</p>
        @endif
        @if ($round['needsPassword'])
            <label for="survey-password" style="margin-top:16px">Mật khẩu do giáo viên cung cấp</label>
            <input id="survey-password" name="password" type="password" autocomplete="off" required maxlength="100" @error('entry') aria-invalid="true" @enderror aria-describedby="contest-password-help">
            <p id="contest-password-help" class="contest-dialog-help">Nhập lại mật khẩu mỗi khi bắt đầu hoặc tiếp tục làm bài.</p>
        @endif
        <p class="contest-dialog-help">{{ $survey ? 'Làm theo bộ câu hỏi chung của kỳ; tổng kết và lưu riêng mỗi lượt khảo sát.' : ($resume ? 'Khôi phục bài đang làm. Lượt thi được ghi nhận khi nộp toàn bộ đề.' : 'Làm theo đề, nộp một lần. Kết quả gồm tổng điểm, điểm từng câu và thứ hạng.') }}</p>
        @error('entry')
            <p role="alert" class="contest-error">{{ $message }}</p>
        @enderror
        <button class="contest-btn primary" type="submit">{{ $survey ? 'Xác nhận & làm bài' : ($resume ? 'Mở bài đang làm' : 'Bắt đầu làm bài') }}<x-lucide name="arrow-right" /></button>
    </form>
</dialog>

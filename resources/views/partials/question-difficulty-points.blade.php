{{--
    SỬA 1/10 (khách: "trong tạo câu hỏi và cập nhật câu hỏi giúp tôi với chỗ độ khó nữa khi chọn
    cơ bản là 2 điểm, Dễ là 4 điểm, khá 6 điểm, khó 8 điểm, rất khó 10 điểm và khi chọn thì nó tự
    active vô field điểm luôn không cho nhập điểm và mặt UI thì cho chọn độ khó rồi mới hiển thị
    điểm dưới độ khó").

    Khối "Độ khó + Điểm" dùng CHUNG cho cả 3 form câu hỏi (admin Tạo, admin Sửa, giáo viên) —
    gộp về 1 partial vì bảng quy đổi điểm nằm ở 3 chỗ là chắc chắn có ngày lệch nhau.

    Biến truyền vào:
      · $selected  mã độ khó đang chọn ('' = chưa có -> dùng mức đầu tiên).

    3 điểm cần biết khi sửa khối này:
      1. Ô Điểm là `readonly`, KHÔNG phải `disabled`. Ô `disabled` thì trình duyệt KHÔNG gửi lên,
         server nhận thiếu 'points' và câu hỏi thành 0 điểm.
      2. Dù vậy server vẫn KHÔNG tin ô này: điểm được tính lại từ độ khó ở
         ContentService/Teacher\QuestionService (ô readonly vẫn sửa được bằng DevTools).
      3. Bỏ hẳn lựa chọn "— Tự suy theo điểm —" cũ: điểm giờ do độ khó quyết định nên để trống
         độ khó là tự mâu thuẫn. Câu CŨ chưa đặt độ khó thì form Sửa điền sẵn mức đang hiển thị
         (QuestionDifficulty::resolve) để không nhảy mức ngoài ý muốn.
--}}
@php
    $dpLevels = \App\Support\QuestionDifficulty::LEVELS;
    $dpPoints = \App\Support\QuestionDifficulty::POINTS;
    $dpSelected = (string) ($selected ?? '');
    $dpSelected = isset($dpLevels[$dpSelected]) ? $dpSelected : (string) array_key_first($dpLevels);
    $dpSelected = (string) old('difficulty', $dpSelected);
    $dpSelected = isset($dpLevels[$dpSelected]) ? $dpSelected : (string) array_key_first($dpLevels);
@endphp

<div x-data="{ diff: '{{ $dpSelected }}', map: {{ \Illuminate\Support\Js::from($dpPoints) }} }" class="space-y-4">
    <div>
        <label class="block text-[13px] text-slate-600 mb-1" for="difficulty">Độ khó</label>
        <x-ws.select id="difficulty" name="difficulty" x-model="diff" required>
            @foreach ($dpLevels as $dkey => $dlabel)
                <option value="{{ $dkey }}" @selected($dpSelected === $dkey)>{{ $dlabel }} — {{ $dpPoints[$dkey] }} điểm</option>
            @endforeach
        </x-ws.select>
    </div>

    <div>
        <label class="block text-[13px] text-slate-600 mb-1" for="points">Điểm</label>
        <input id="points" name="points" type="number" readonly
               value="{{ $dpPoints[$dpSelected] }}" :value="map[diff]"
               class="admin-input bg-slate-50 text-slate-500">
        <p class="text-xs text-slate-400 mt-1">Điểm do Độ khó quyết định, không nhập tay: Cơ bản 2 · Dễ 4 · Khá 6 · Khó 8 · Rất khó 10.</p>
    </div>
</div>

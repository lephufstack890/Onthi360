{{--
    SỬA 25/10 (khách: "nút Thêm kỳ thi / Thêm kỳ khảo sát, bấm thì hiện modal như UI source") — hộp thoại Thêm / Sửa kỳ,
    chép bố cục ContestRoundDialog.jsx: Người chủ trì · Tên kỳ · Bắt đầu / Kết thúc (GMT+7) · Nguồn câu hỏi (cuộc thi chỉ
    "Từ đề có sẵn"; khảo sát thêm "Từ nguồn câu hỏi") · gợi ý bước tiếp theo. Tạo xong mở tab "Lấy đề có sẵn" / "Thiết lập
    đề" để chọn đề. Gửi về competitions.manage.rounds.* → ContestOrganizerService::saveRound() kiểm quyền + dữ liệu.
--}}
@php $re = $roundEditor; $sv = $re['isSurvey']; $word = $sv ? 'kỳ khảo sát' : 'kỳ thi'; @endphp
<dialog x-ref="roundDialog" class="contest-ui contest-round-dialog" aria-labelledby="round-create-title" @click="if ($event.target === $refs.roundDialog) closeRound()">
    <form method="POST" :action="roundForm.url">
        @csrf
        <header>
            <span class="contest-icon teal"><span x-show="!roundForm.id"><x-lucide name="plus" /></span><span x-show="roundForm.id" x-cloak><x-lucide name="pencil" /></span></span>
            <div>
                <h2 id="round-create-title" x-text="(roundForm.id ? 'Sửa ' : 'Thêm ') + '{{ $word }}'">Thêm {{ $word }}</h2>
                <p>{{ $re['contestTitle'] }}</p>
            </div>
            <button type="button" aria-label="Đóng" @click="closeRound()"><x-lucide name="x" /></button>
        </header>

        <div class="round-form-body">
            @if (session('round_dialog') && $errors->hasAny(['title', 'starts_at', 'ends_at']))
                <p class="contest-error" role="alert">{{ $errors->first('title') ?: ($errors->first('starts_at') ?: $errors->first('ends_at')) }}</p>
            @endif
            <div class="round-organizer"><span>Người chủ trì</span><strong x-text="roundForm.host">{{ $re['organizerName'] }}</strong></div>

            <label class="round-field">Tên {{ $word }}
                <input type="text" name="title" required maxlength="160" x-model="roundForm.title"
                       placeholder="{{ $sv ? 'Ví dụ: Khảo sát đầu vào lớp Python 9A' : 'Ví dụ: Đánh giá cuối học phần 1' }}">
            </label>
            <div class="round-date-fields">
                <label class="round-field">Bắt đầu (GMT+7)<input type="datetime-local" name="starts_at" required x-model="roundForm.startsAt"></label>
                <label class="round-field">Kết thúc (GMT+7)<input type="datetime-local" name="ends_at" required x-model="roundForm.endsAt"></label>
            </div>

            <template x-if="!roundForm.id">
                <div class="round-source-block">
                    <fieldset class="round-source-options"><legend>Nguồn câu hỏi</legend>
                        <label :data-selected="roundForm.source === 'exam' ? 'true' : 'false'">
                            <input type="radio" name="source_type" value="exam" x-model="roundForm.source"><x-lucide name="file-text" />
                            <span><b>Từ đề có sẵn</b><small>{{ $sv ? 'Chọn đề từ ngân hàng đề ở bước tiếp theo' : 'Kỳ thi luôn chọn đề từ ngân hàng đề' }}</small></span>
                        </label>
                        @if ($sv)
                            <label :data-selected="roundForm.source === 'questions' ? 'true' : 'false'">
                                <input type="radio" name="source_type" value="questions" x-model="roundForm.source"><x-lucide name="list-checks" />
                                <span><b>Từ nguồn câu hỏi</b><small>Thiết lập ma trận đề ở bước tiếp theo</small></span>
                            </label>
                        @endif
                    </fieldset>
                    <div class="round-matrix-next">
                        <span x-show="roundForm.source === 'exam'"><x-lucide name="file-text" /></span><span x-show="roundForm.source !== 'exam'" x-cloak><x-lucide name="list-checks" /></span>
                        <div>
                            <b x-text="roundForm.source === 'exam' ? 'Tiếp theo: chọn đề từ ngân hàng đề' : 'Tiếp theo: thiết lập ma trận đề'">Tiếp theo: chọn đề từ ngân hàng đề</b>
                            <p x-text="roundForm.source === 'exam' ? 'Tìm và chọn đề, xem đề đã được bạn sử dụng ở kỳ nào trước khi lưu.' : 'Chọn nguồn câu hỏi, chuyên đề, độ khó, số câu và điểm; sau đó sinh, duyệt và chốt đề.'"></p>
                        </div>
                    </div>
                </div>
            </template>
            <p class="round-help" x-show="roundForm.id" x-cloak>Đổi đề của kỳ ở tab <b>{{ $sv ? 'Thiết lập đề / Lấy đề có sẵn' : 'Lấy đề có sẵn' }}</b>.</p>
        </div>

        <footer>
            <button type="submit" class="contest-btn secondary crd-delete" x-show="roundForm.id && !roundForm.locked" x-cloak formnovalidate
                    :formaction="roundForm.deleteUrl" onclick="return confirm('Xoá {{ $word }} này? Các đề sinh từ ma trận của kỳ (chưa ai làm) cũng bị xoá.');">
                <x-lucide name="trash-2" />Xoá kỳ
            </button>
            <span class="crd-spacer"></span>
            <button type="button" class="contest-btn secondary" @click="closeRound()">Hủy</button>
            <button type="submit" class="contest-btn primary" x-text="roundForm.id ? 'Lưu thay đổi' : (roundForm.source === 'exam' ? 'Tạo kỳ & chọn đề' : 'Tạo kỳ & lập ma trận')">Tạo kỳ &amp; chọn đề</button>
        </footer>
    </form>
</dialog>

{{--
    SỬA 25/10 (khách: "lấy từ đề có sẵn thì chữ Lấy đề có sẵn … modal như UI source") — hộp thoại chọn đề cho kỳ, chép bố cục
    ContestPaperSettings.jsx: 1 Nguồn đề → 2 Chọn bộ đề → 3 Cách giao đề. Danh sách đề + lịch sử "Bạn đã dùng · N kỳ khác"
    lấy từ ContestOrganizerService::roundEditor()['options']. Gửi về competitions.manage.rounds.exam → setRoundExam().
    Cuộc thi: 1 đề + số mã đề hoán vị (cùng câu, đổi thứ tự) + cách giao. Khảo sát: chọn nguồn (đề có sẵn / nguồn câu hỏi).
--}}
@php $re = $roundEditor; $sv = $re['isSurvey']; @endphp
<dialog x-ref="pickerDialog" class="contest-ui contest-round-dialog round-paper-dialog" aria-labelledby="round-paper-title" @click="if ($event.target === $refs.pickerDialog) closePicker()">
    <form method="POST" :action="picker.url" class="round-paper-shell">
        @csrf
        <header>
            <span class="contest-icon teal"><x-lucide name="file-text" /></span>
            <div><h2 id="round-paper-title">Thiết lập đề</h2><p>{{ $round['label'] }}</p></div>
            <button type="button" aria-label="Đóng thiết lập đề" @click="closePicker()"><x-lucide name="x" /></button>
        </header>

        <div class="round-form-body">
            <ol class="paper-setup-steps"><li><b>1</b>Nguồn đề</li><li><b>2</b><span x-text="picker.source === 'questions' ? 'Ma trận & bộ đề' : 'Chọn bộ đề'">Chọn bộ đề</span></li><li><b>3</b>Cách giao đề</li></ol>
            @if (session('exam_picker') && $errors->has('assessment_id'))
                <p class="contest-error" role="alert">{{ $errors->first('assessment_id') }}</p>
            @endif
            <p class="survey-builder-warning" role="status" x-show="picker.locked" x-cloak>Kỳ thi đã có bài làm — giữ nguyên đề để công bằng cho mọi học sinh.</p>

            @if ($sv)
                <fieldset class="round-source-options"><legend>Chọn nguồn để tạo bộ đề</legend>
                    <label :data-selected="picker.source === 'exam' ? 'true' : 'false'"><input type="radio" name="source_type" value="exam" x-model="picker.source"><x-lucide name="file-text" /><span><b>Từ đề có sẵn</b><small>Chọn một hoặc nhiều đề Loại "{{ $re['bankTypeLabel'] ?? 'Đề thi' }}" trong ngân hàng</small></span></label>
                    <label :data-selected="picker.source === 'questions' ? 'true' : 'false'"><input type="radio" name="source_type" value="questions" x-model="picker.source"><x-lucide name="list-checks" /><span><b>Từ nguồn câu hỏi</b><small>Lập ma trận và sinh số đề theo nhu cầu</small></span></label>
                </fieldset>
            @else
                <input type="hidden" name="source_type" value="exam">
            @endif

            <section class="round-exam-picker" aria-label="Chọn đề có sẵn" x-show="picker.source === 'exam'">
                <h3>Chọn đề từ ngân hàng đề <small>· chỉ đề Loại "{{ $re['bankTypeLabel'] ?? 'Đề thi' }}" · chọn được nhiều đề</small></h3>
                {{-- SỬA 25/10 — chọn nhiều đề: học sinh được bốc ngẫu nhiên 1 đề. --}}
                <p class="round-help round-pick-note" role="status" x-show="picker.ids.length" x-cloak>
                    <b x-text="'Đã chọn ' + picker.ids.length + ' đề'"></b>
                    <span x-show="picker.ids.length > 1">{{ $sv ? ' · Mỗi lượt khảo sát, hệ thống bốc ngẫu nhiên 1 đề cho học sinh.' : ' · Mỗi học sinh được bốc ngẫu nhiên 1 đề khi vào thi; đề đã nhận được giữ cố định.' }}</span>
                </p>
                <label class="round-exam-search"><x-lucide name="search" /><input type="search" aria-label="Tìm đề có sẵn" placeholder="Tìm tên hoặc mã đề…" x-model="picker.q"></label>
                <div class="round-exam-list">
                    <template x-for="o in pickerOptions" :key="o.id">
                        <div class="round-exam-option" :data-selected="picker.ids.includes(String(o.id)) ? 'true' : 'false'">
                            <label>
                                <input type="checkbox" name="assessment_ids[]" :value="o.id" :checked="picker.ids.includes(String(o.id))" @change="toggleExamPick(o.id)"
                                       :disabled="picker.locked || (! picker.ids.includes(String(o.id)) && picker.ids.length >= maxBank)">
                                <span>
                                    <b x-text="o.title"></b>
                                    <small x-text="(o.isPdf ? 'Đề PDF' : (o.questions + ' câu')) + ' · ' + (o.points || 0) + ' điểm' + (o.duration ? ' · ' + o.duration + ' phút' : '')"></small>
                                    <em :class="usesOther(o).length ? 'round-used' : 'round-unused'" x-text="usesOther(o).length ? ('Bạn đã dùng · ' + usesOther(o).length + ' kỳ khác') : 'Bạn chưa dùng ở kỳ khác'"></em>
                                </span>
                            </label>
                            <template x-if="usesOther(o).length">
                                <details><summary>Các kỳ đã sử dụng</summary>
                                    <ul><template x-for="u in usesOther(o)" :key="u.roundId"><li><b x-text="u.label"></b><span x-text="u.date + ' · ' + u.contest"></span></li></template></ul>
                                </details>
                            </template>
                        </div>
                    </template>
                </div>
                <p class="round-help" role="status" x-show="!pickerOptions.length">Không tìm thấy đề phù hợp. Thử từ khoá khác hoặc tạo đề Loại "{{ $re['bankTypeLabel'] ?? 'Đề thi' }}" trong Tài liệu.</p>
                <p class="round-help">Lịch sử chỉ tính các kỳ bạn chủ trì. Có thể dùng lại đề cho nhiều kỳ.</p>
            </section>
            <p class="round-help" x-show="picker.source === 'questions'" x-cloak>Chọn nguồn, chuyên đề, độ khó, số câu và điểm bằng ma trận ở tab <b>Thiết lập đề</b>. Học sinh chỉ bắt đầu khi bạn đã chốt đề. Đề có sẵn đang gắn (nếu có) sẽ được gỡ khỏi kỳ.</p>

            @if (! $sv && $re['formsSupported'])
                <label class="round-field" x-show="picker.source === 'exam' && picker.ids.length <= 1">Số mã đề hoán vị
                    <select name="form_count" x-model="picker.formCount" :disabled="picker.locked">
                        @foreach (range(1, $re['maxForms']) as $n)
                            <option value="{{ $n }}">{{ $n === 1 ? '1 đề — không hoán vị' : $n.' đề (Đề 101 → '.(100 + $n).')' }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="paper-delivery-options" :disabled="picker.locked" x-show="picker.ids.length <= 1">
                    <legend>Cách giao đề cho học sinh</legend>
                    <div>
                        <label :data-selected="picker.mode === 'choice' ? 'true' : 'false'"><input type="radio" name="assignment_mode" value="choice" x-model="picker.mode"><x-lucide name="list-checks" /><span><b>Học sinh tự chọn</b><small x-text="'Chọn một trong ' + picker.formCount + ' đề trước khi bắt đầu.'"></small></span></label>
                        <label :data-selected="picker.mode === 'random' ? 'true' : 'false'"><input type="radio" name="assignment_mode" value="random" x-model="picker.mode"><x-lucide name="shuffle" /><span><b>Giao ngẫu nhiên</b><small>Hệ thống giao một đề khi học sinh vào làm.</small></span></label>
                    </div>
                    <p><span x-show="picker.formCount === '1'">Hiện có một đề: mọi học sinh sẽ nhận đề này. </span>Các mã đề dùng cùng câu hỏi và thang điểm, chỉ đổi thứ tự câu; đề đã nhận được giữ nguyên.</p>
                </fieldset>
            @endif
        </div>

        <footer>
            <button type="button" class="contest-btn secondary" @click="closePicker()">Đóng</button>
            <button type="submit" class="contest-btn primary" x-show="!picker.locked" :disabled="picker.source === 'exam' && !picker.ids.length"
                    x-text="picker.source === 'questions' ? 'Tiếp tục lập ma trận' : 'Lưu thiết lập đề'">Lưu thiết lập đề</button>
        </footer>
    </form>
</dialog>

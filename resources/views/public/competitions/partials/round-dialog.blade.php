{{--
    SỬA 24/10 (khách: "admin và giáo viên có nút thêm kỳ thi ngay trong phòng thi, bấm thì hiện modal, thiết kế đẹp, dễ
    dùng") — hộp thoại Thêm / Sửa kỳ thi. Gửi về competitions.manage.rounds.* (ContestManageController), quyền + luật kiểm
    ở ContestOrganizerService::saveRound(). Dữ liệu: ContestOrganizerService::roundEditor() ($roundEditor).
--}}
@php $re = $roundEditor; @endphp
<dialog x-ref="roundDialog" class="contest-ui crd" aria-labelledby="crd-title" @click="if ($event.target === $refs.roundDialog) closeRound()">
    <form method="POST" :action="roundForm.url" class="crd-form">
        @csrf
        <header class="crd-head">
            <span class="contest-icon {{ $re['isSurvey'] ? 'teal' : 'amber' }}"><x-lucide :name="$re['isSurvey'] ? 'key-round' : 'trophy'" /></span>
            <div>
                <h2 id="crd-title" x-text="roundForm.id ? 'Sửa kỳ thi' : 'Thêm kỳ thi'">Thêm kỳ thi</h2>
                <p>{{ $contest['title'] }}</p>
            </div>
            <button type="button" class="crd-close" aria-label="Đóng" @click="closeRound()"><x-lucide name="x" /></button>
        </header>

        <div class="crd-body">
            @if ($errors->any() && session('round_dialog'))
                <p class="contest-error" role="alert">{{ $errors->first() }}</p>
            @endif
            <p class="crd-locked" x-show="roundForm.locked" x-cloak><x-lucide name="lock-keyhole" />Kỳ thi đã có bài làm — vẫn sửa được tên và thời gian, nhưng không đổi đề.</p>

            <label class="crd-field">
                <span>Tên kỳ thi</span>
                <input type="text" name="title" maxlength="255" x-model="roundForm.title" placeholder="{{ $re['isSurvey'] ? 'VD: Khảo sát kiến thức nền tảng' : 'VD: Vòng sơ loại' }}">
                <small>Để trống sẽ dùng tên đề.</small>
            </label>

            <div class="crd-grid">
                <label class="crd-field"><span>Bắt đầu</span><input type="datetime-local" name="starts_at" x-model="roundForm.startsAt"></label>
                <label class="crd-field"><span>Kết thúc</span><input type="datetime-local" name="ends_at" x-model="roundForm.endsAt"></label>
            </div>
            <div class="crd-presets" aria-label="Chọn nhanh thời lượng">
                <span>Chọn nhanh:</span>
                <button type="button" @click="presetRound(0)">Bắt đầu ngay</button>
                <button type="button" @click="presetRound(1)">+1 ngày</button>
                <button type="button" @click="presetRound(7)">+1 tuần</button>
                <button type="button" @click="presetRound(30)">+1 tháng</button>
            </div>

            <div class="crd-field">
                <span>{{ $re['isSurvey'] ? 'Đề của kỳ' : 'Đề thi' }}</span>
                @if ($re['isSurvey'])
                    <div class="crd-choice">
                        <label :class="roundForm.source === 'matrix' ? 'is-on' : ''">
                            <input type="radio" value="matrix" x-model="roundForm.source" :disabled="roundForm.locked">
                            <b><x-lucide name="shuffle" />Sinh đề từ ngân hàng</b>
                            <small>Thiết lập ma trận ở tab "Thiết lập đề", có thể tạo nhiều đề cho kỳ.</small>
                        </label>
                        <label :class="roundForm.source === 'existing' ? 'is-on' : ''">
                            <input type="radio" value="existing" x-model="roundForm.source" :disabled="roundForm.locked">
                            <b><x-lucide name="file-text" />Dùng đề có sẵn</b>
                            <small>Chọn một đề loại "Đề thi" trong Tài liệu.</small>
                        </label>
                    </div>
                @endif
                <div x-show="{{ $re['isSurvey'] ? "roundForm.source === 'existing'" : 'true' }}" class="crd-picker">
                    <div class="crd-search"><x-lucide name="search" /><input type="search" placeholder="Tìm đề theo tên…" x-model="roundForm.q" :disabled="roundForm.locked"></div>
                    <select name="assessment_id" x-model="roundForm.assessmentId" size="6" :disabled="roundForm.locked" class="crd-list">
                        <option value="">{{ $re['isSurvey'] ? '— Chưa chọn —' : '— Chọn đề —' }}</option>
                        <template x-for="o in filteredOptions" :key="o.id">
                            <option :value="String(o.id)" :selected="String(o.id) === roundForm.assessmentId" x-text="o.title + (o.duration ? ' · ' + o.duration + ' phút' : '')"></option>
                        </template>
                    </select>
                    <small x-show="!filteredOptions.length">Không có đề phù hợp. Tạo đề loại "Đề thi" ở Tài liệu trước.</small>
                </div>
                {{-- Khoá đề khi đã có bài làm: vẫn gửi đúng đề cũ. --}}
                <template x-if="roundForm.locked"><input type="hidden" name="assessment_id" :value="roundForm.assessmentId"></template>
                @if ($re['isSurvey'])
                    <template x-if="roundForm.source === 'matrix' && !roundForm.locked"><input type="hidden" name="assessment_id" value=""></template>
                @endif
            </div>

            @if (! $re['isSurvey'] && $re['formsSupported'])
                <div class="crd-grid">
                    <label class="crd-field"><span>Số mã đề hoán vị</span>
                        <select name="form_count" x-model="roundForm.formCount">
                            @foreach (range(1, $re['maxForms']) as $n)
                                <option value="{{ $n }}">{{ $n === 1 ? '1 — không hoán vị' : $n.' mã đề (Đề 101 → '.(100 + $n).')' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="crd-field"><span>Cách giao mã đề</span>
                        <select name="assignment_mode" x-model="roundForm.assignmentMode">
                            <option value="random">Hệ thống giao ngẫu nhiên</option>
                            <option value="choice">Học sinh tự chọn mã đề</option>
                        </select>
                    </label>
                </div>
                <small class="crd-note">Các mã đề dùng cùng câu hỏi và thang điểm, chỉ đổi thứ tự câu. Mỗi học sinh nộp 1 lần mỗi kỳ.</small>
            @endif
        </div>

        <footer class="crd-foot">
            <button type="submit" class="contest-btn secondary crd-delete" x-show="roundForm.id && !roundForm.locked" x-cloak formnovalidate
                    :formaction="roundForm.deleteUrl" onclick="return confirm('Xoá kỳ thi này? Các đề sinh từ ma trận của kỳ (chưa ai làm) cũng bị xoá.');">
                <x-lucide name="trash-2" />Xoá kỳ thi
            </button>
            <span class="crd-spacer"></span>
            <button type="button" class="contest-btn secondary" @click="closeRound()">Huỷ</button>
            <button type="submit" class="contest-btn primary"><x-lucide name="check-circle-2" /><span x-text="roundForm.id ? 'Lưu kỳ thi' : 'Thêm kỳ thi'">Thêm kỳ thi</span></button>
        </footer>
    </form>
</dialog>

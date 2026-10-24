{{-- SỬA 24/10 — hộp thoại "Thêm học sinh" (source: AddStudentsDialog). Danh sách tải qua competitions.manage.students
     (admin: mọi học sinh đang hoạt động; giáo viên: học sinh lớp mình dạy). --}}
@php $p = $participants; @endphp
<dialog x-ref="addDialog" class="contest-ui contest-add-students" aria-labelledby="add-students-title" @click="if ($event.target === $refs.addDialog) closeAdd()">
    <form method="POST" action="{{ $p['addUrl'] }}">
        @csrf
        <input type="hidden" name="vong" value="{{ $round['id'] }}">
        <header><div><h2 id="add-students-title">Thêm học sinh</h2><p>{{ $contest['title'] }}</p></div><button type="button" aria-label="Đóng thêm học sinh" @click="closeAdd()"><x-lucide name="x" /></button></header>
        <div class="contest-student-picker">
            <p class="hub-assessment-note">{{ $viewer['isAdmin'] ? 'Chọn tài khoản học sinh đang hoạt động trong hệ thống.' : 'Chọn học sinh trong lớp bạn phụ trách.' }} Áp dụng cho toàn bộ các kỳ của sự kiện.</p>
            <div class="contest-student-filters">
                <label><span>Tìm học sinh</span><input type="search" aria-label="Tìm học sinh" placeholder="Họ tên hoặc tài khoản…" x-model="addQuery" @input.debounce.350ms="search()"></label>
                <label><span>Lớp học</span>
                    <select aria-label="Lớp học" x-model="addClass" @change="search()">
                        <option value="">{{ $viewer['isAdmin'] ? 'Tất cả học sinh' : 'Tất cả lớp được quản lý' }}</option>
                        @foreach ($p['classes'] as $cl)
                            <option value="{{ $cl['id'] }}">{{ $cl['name'] }}{{ $cl['code'] ? ' · '.$cl['code'] : '' }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="contest-select-all"><input type="checkbox" :checked="allPicked" :disabled="!selectable.length" @change="toggleAll()">Chọn tất cả kết quả (<span x-text="selectable.length"></span>)<span>Đã chọn <b x-text="picked.length"></b></span></label>
            <div class="contest-student-options">
                <p class="is-loading" x-show="loading">Đang tải danh sách…</p>
                <template x-for="s in students" :key="s.id">
                    <label>
                        <input type="checkbox" :aria-label="'Chọn ' + s.name" :checked="s.added || picked.includes(s.id)" :disabled="s.added" @change="togglePick(s.id)">
                        <span><b x-text="s.name"></b><small x-text="(s.account || '—') + ' · ' + (s.className || 'Chưa cập nhật lớp')"></small></span>
                        <em x-show="s.added">Đã có</em>
                    </label>
                </template>
                <p x-show="!loading && !students.length" x-text="loadError || 'Không có học sinh phù hợp trong phạm vi được quản lý.'"></p>
            </div>
            <template x-for="id in picked" :key="'h' + id"><input type="hidden" name="student_ids[]" :value="id"></template>
            <p class="hub-sample-note">{{ $survey ? 'Thêm vào danh sách theo dõi. Học sinh vẫn nhập mật khẩu khi khảo sát; mỗi lượt làm được lưu riêng.' : 'Học sinh được thêm sẽ được duyệt tham gia ngay, không phải gửi đăng ký lại. Vẫn áp dụng thời gian thi và một lượt nộp mỗi kỳ.' }}</p>
        </div>
        <footer>
            <button type="button" class="contest-btn secondary" @click="closeAdd()">Hủy</button>
            <button type="submit" class="contest-btn primary" :disabled="!picked.length"><x-lucide name="user-plus" />{{ $survey ? 'Thêm vào danh sách' : 'Thêm & duyệt tham gia' }} (<span x-text="picked.length">0</span>)</button>
        </footer>
    </form>
</dialog>

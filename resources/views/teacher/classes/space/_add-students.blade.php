{{-- SỬA 6/11 — nút + popup "Thêm học sinh" (tách từ tab Học sinh cũ, logic giữ nguyên: window.tamAdd ở cuối show.blade.php). --}}
<div x-data="tamAdd(@js(['url' => route('teacher.classes.students.search', $classRoom->id), 'open' => $errors->has('contacts'), 'contacts' => old('contacts', '')]))" @keydown.escape.window="open && close()">
    <button type="button" class="csp-btn grn" @click="openModal()"><x-lucide name="user-plus" />Thêm học sinh</button>

    <template x-teleport="body">
        <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
            <form class="tam-dlg" method="POST" action="{{ route('teacher.classes.members.add', $classRoom->id) }}" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true" aria-labelledby="tam-title" @submit="submitting = true">
                @csrf
                <template x-for="s in selected" :key="'h' + s.id"><input type="hidden" name="student_ids[]" :value="s.id"></template>

                <header class="tam-head">
                    <span class="tam-hic"><x-lucide name="user-plus" /></span>
                    <div class="tam-htx">
                        <h3 id="tam-title">Thêm học sinh vào lớp</h3>
                        <p>{{ $classRoom->name }}</p>
                    </div>
                    <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                </header>

                <div class="tam-seg" role="tablist">
                    <button type="button" :class="{ on: mode === 'pick' }" @click="mode = 'pick'"><x-lucide name="search" />Chọn từ danh sách</button>
                    <button type="button" :class="{ on: mode === 'contact' }" @click="mode = 'contact'"><x-lucide name="mail" />Nhập email / SĐT</button>
                </div>

                <div class="tam-body" x-show="mode === 'pick'">
                    <div class="tam-combo" @click.outside="drop = false">
                        <label class="tam-input" :class="{ on: drop }">
                            <x-lucide name="search" />
                            <input type="text" x-ref="q" x-model="q" @input="search()" @focus="drop = true" @keydown.enter.prevent="pickFirst()"
                                   placeholder="Tìm theo tên, email hoặc số điện thoại…" autocomplete="off" aria-label="Tìm học sinh">
                            <span class="tam-spin" x-show="loading"></span>
                            <button type="button" class="tam-caret" @click="drop = !drop" :aria-expanded="drop" aria-label="Mở danh sách"><x-lucide name="chevron-down" /></button>
                        </label>
                        <div class="tam-drop" x-show="drop" x-transition.origin.top>
                            <div class="tam-drop-h">
                                <span x-text="q.trim() ? (results.length + ' kết quả') : 'Học sinh mới đăng ký gần đây'"></span>
                                <button type="button" x-show="selectable.length > 1" @click="selectAll()" x-text="allPicked ? 'Bỏ chọn tất cả' : 'Chọn tất cả (' + selectable.length + ')'"></button>
                            </div>
                            <ul class="tam-list" role="listbox" aria-multiselectable="true">
                                <template x-for="u in results" :key="u.id">
                                    <li role="option" :aria-selected="isSel(u)" :class="{ sel: isSel(u), dis: u.inClass }" @click="toggle(u)">
                                        <span class="tam-ck"><x-lucide name="check" /></span>
                                        <span class="tam-av" :class="'t' + (u.id % 4)"><img x-show="u.avatar" :src="u.avatar" alt=""><b x-show="!u.avatar" x-text="u.initials"></b></span>
                                        <span class="tam-who"><b x-text="u.name"></b><small x-text="[u.contact, u.province].filter(Boolean).join(' • ') || 'Chưa có email / SĐT'"></small></span>
                                        <span class="tam-tag" x-show="u.inClass">Đã trong lớp</span>
                                    </li>
                                </template>
                            </ul>
                            <p class="tam-empty" x-show="!loading && results.length === 0" x-text="q.trim() ? 'Không tìm thấy học sinh phù hợp. Thử tên khác, hoặc chuyển sang “Nhập email / SĐT”.' : 'Chưa có tài khoản học sinh nào.'"></p>
                        </div>
                    </div>

                    <div class="tam-picked">
                        <div class="tam-picked-h">
                            <b>Đã chọn <span x-text="selected.length"></span> học sinh</b>
                            <button type="button" x-show="selected.length" @click="selected = []">Bỏ chọn hết</button>
                        </div>
                        <div class="tam-chips" x-show="selected.length">
                            <template x-for="s in selected" :key="'c' + s.id">
                                <span class="tam-chip">
                                    <span class="tam-av sm" :class="'t' + (s.id % 4)"><img x-show="s.avatar" :src="s.avatar" alt=""><b x-show="!s.avatar" x-text="s.initials"></b></span>
                                    <span x-text="s.name"></span>
                                    <button type="button" @click="remove(s.id)" :aria-label="'Bỏ chọn ' + s.name"><x-lucide name="x" /></button>
                                </span>
                            </template>
                        </div>
                        <p class="tam-hint" x-show="!selected.length">Gõ tên vào ô tìm kiếm rồi bấm để chọn — chọn được nhiều em cùng lúc.</p>
                    </div>
                </div>

                <div class="tam-body" x-show="mode === 'contact'" x-cloak>
                    <label class="tam-lb" for="tam-contacts">Nhập <b>email</b> hoặc <b>số điện thoại</b> học sinh đã dùng để đăng ký tài khoản — mỗi dòng một người (tối đa 50).</label>
                    <textarea id="tam-contacts" name="contacts" rows="6" x-model="contacts" class="tam-ta" placeholder="vd: nguyenvana@gmail.com&#10;0912 345 678"></textarea>
                </div>

                @error('contacts')<p class="tam-err"><x-lucide name="alert-triangle" />{{ $message }}</p>@enderror

                <footer class="tam-foot">
                    <small><x-lucide name="info" />Học sinh vào lớp ngay, không cần chờ duyệt, và nhận được thông báo.</small>
                    <div class="tam-acts">
                        <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                        <button type="submit" class="tam-btn pri" :disabled="!canSubmit || submitting">
                            <x-lucide name="user-plus" /><span x-text="submitting ? 'Đang thêm…' : (count > 0 ? 'Thêm ' + count + ' học sinh' : 'Thêm vào lớp')"></span>
                        </button>
                    </div>
                </footer>
            </form>
        </div>
    </template>
</div>

@extends('layouts.student')

@section('title', 'Phụ huynh')
@section('page-title', 'Phụ huynh')

@section('content')
    {{-- SỬA 12/10 — MỤC MỚI theo education-main (source mới): StudentParentsPanel.jsx — "Phụ huynh của tôi": thẻ "Phụ huynh đã liên kết"
         (hủy liên kết có xác nhận), thẻ "Liên kết phụ huynh" (nhập email + mối quan hệ → Tìm → kiểm tra tên → Xác nhận liên kết),
         thẻ "Yêu cầu từ phụ huynh" (Đồng ý / Từ chối). Logic thật: Student\StudentParentLinkService (bảng parent_links, quyền xem của phụ huynh
         vẫn chỉ dựa vào status = verified). --}}
    @php
        $linked = $linked ?? [];
        $requests = $requests ?? [];
        $relationships = $relationships ?? ['Mẹ', 'Bố', 'Người giám hộ'];
        $cfg = [
            'search' => route('student.parents.search'),
            'store' => route('student.parents.store'),
            'respond' => url('student/phu-huynh').'/__ID__/phan-hoi',
            'destroy' => url('student/phu-huynh').'/__ID__',
            'linked' => $linked,
            'requests' => $requests,
        ];
    @endphp

    <div class="sw student-overview student-parents" data-section="STUDENT-PARENTS" x-data="stuParents(@js($cfg))" x-init="init()">
        @include('student._hero')

        <div class="student-overview-heading">
            <h2 class="student-overview-title"><x-lucide name="users" class="h-4 w-4" />Phụ huynh của tôi</h2>
        </div>

        <p role="alert" class="student-family-alert" x-show="error" x-text="error" x-cloak></p>
        <p role="status" class="student-family-notice" x-show="notice" x-text="notice" x-cloak></p>

        <div class="student-overview-grid student-family-grid">
            <section class="student-overview-card student-family-linked">
                <div class="student-overview-heading">
                    <h2 class="student-overview-title"><x-lucide name="check-circle-2" class="h-4 w-4" />Phụ huynh đã liên kết</h2>
                    <span class="student-overview-muted"><span x-text="linked.length"></span> phụ huynh</span>
                </div>
                <p class="student-overview-empty" x-show="linked.length === 0">Chưa có phụ huynh đã liên kết. Nhập email phụ huynh để bắt đầu.</p>
                <template x-for="l in linked" :key="l.id">
                    <article class="student-family-row" :aria-label="l.name">
                        <div class="student-connection-person">
                            <span class="student-connection-avatar"><x-lucide name="users" class="h-4 w-4" /></span>
                            <div><h3 x-text="l.name"></h3><p x-text="l.relationship"></p></div>
                            <span class="student-connected" x-text="l.active ? 'Đã liên kết' : 'Tài khoản không hoạt động'"></span>
                        </div>
                        <p class="student-overview-muted" x-show="l.email" x-text="l.email"></p>
                        <div class="student-overview-heading">
                            <p class="student-overview-muted">Liên kết từ <span x-text="l.since || '—'"></span></p>
                            <button type="button" class="student-family-remove" :disabled="busy" @click="cancelId = l.id; error = ''; notice = ''">Hủy liên kết</button>
                        </div>
                        <div class="student-family-confirm" role="group" aria-label="Xác nhận hủy liên kết" x-show="cancelId === l.id" x-cloak>
                            <p>Hủy liên kết với <strong x-text="l.name"></strong>? Phụ huynh sẽ không còn theo dõi bài được giao, kết quả và điểm danh của bạn.</p>
                            <div class="student-family-actions">
                                <button type="button" class="student-family-remove" :disabled="busy" @click="unlink(l)">Xác nhận hủy</button>
                                <button type="button" class="student-overview-action" @click="cancelId = null">Giữ liên kết</button>
                            </div>
                        </div>
                    </article>
                </template>
                <p class="student-overview-muted student-family-footnote">Phụ huynh đã liên kết có thể theo dõi bài được giao, kết quả và điểm danh của bạn.</p>
            </section>

            <section class="student-overview-card student-family-link-form">
                <h2 class="student-overview-title"><x-lucide name="link-2" class="h-4 w-4" />Liên kết phụ huynh</h2>
                <p class="student-overview-muted">Tìm đúng tài khoản, kiểm tra tên rồi xác nhận liên kết.</p>
                <form class="student-family-form" @submit.prevent="search()">
                    <label>Email phụ huynh
                        <input type="email" x-model="identifier" maxlength="254" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="Ví dụ: phuhuynh@email.com" :disabled="busy" @input="candidate = null; error = ''; notice = ''">
                    </label>
                    <label>Mối quan hệ
                        <select aria-label="Mối quan hệ" x-model="relationship" :disabled="busy" @change="candidate = null; error = ''">
                            <option value="">Chọn mối quan hệ</option>
                            @foreach ($relationships as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                        </select>
                    </label>
                    <button type="submit" class="student-overview-action" :disabled="busy"><x-lucide name="search" class="h-3.5 w-3.5" />Tìm phụ huynh</button>
                </form>
                <div class="student-family-candidate" role="group" aria-label="Phụ huynh tìm thấy" x-show="candidate" x-cloak>
                    <h3 x-text="candidate && candidate.name"></h3>
                    <p class="student-overview-muted" x-text="relationship + (candidate ? ' · ' + candidate.email : '')"></p>
                    <p class="student-overview-muted">Xác nhận để phụ huynh theo dõi thông tin học tập của bạn.</p>
                    <div class="student-family-actions">
                        <button type="button" class="student-overview-action" :disabled="busy" @click="confirmLink()">Xác nhận liên kết</button>
                        <button type="button" class="student-family-secondary" @click="candidate = null">Bỏ qua</button>
                    </div>
                </div>
                <p class="student-family-help"><x-lucide name="shield-check" class="h-3.5 w-3.5" /><span>Bạn quyết định ai được liên kết. Chỉ tài khoản phụ huynh đang hoạt động mới tìm thấy được.</span></p>
            </section>

            <section class="student-overview-card student-family-requests" x-show="requests.length > 0" x-cloak>
                <h2 class="student-overview-title"><x-lucide name="clock-3" class="h-4 w-4" />Yêu cầu từ phụ huynh <span class="student-overview-muted">(<span x-text="requests.length"></span>)</span></h2>
                <template x-for="r in requests" :key="r.id">
                    <article class="student-family-row" :aria-label="'Yêu cầu từ ' + r.name">
                        <h3 x-text="r.name"></h3>
                        <p class="student-overview-muted" x-text="r.relationship + (r.email ? ' · ' + r.email : '')"></p>
                        <p class="student-overview-muted">Chờ bạn xác nhận · Chưa có quyền theo dõi</p>
                        <div class="student-family-actions">
                            <button type="button" class="student-overview-action" :disabled="busy" @click="respond(r, true)">Đồng ý liên kết</button>
                            <button type="button" class="student-family-remove" :disabled="busy" @click="respond(r, false)">Từ chối</button>
                        </div>
                    </article>
                </template>
            </section>
        </div>
    </div>

    <script>
        function stuParents(cfg) {
            var token = @js(csrf_token());
            function call(method, url, body) {
                return fetch(url, {
                    method: method, credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    body: body ? JSON.stringify(body) : undefined,
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        if (res.ok) { return data; }
                        var msg = data && data.errors ? Object.values(data.errors)[0][0] : (data && data.message) || 'Không thực hiện được. Hãy tải lại trang rồi thử lại.';
                        throw new Error(msg);
                    });
                });
            }
            return {
                linked: cfg.linked, requests: cfg.requests,
                identifier: '', relationship: '', candidate: null, cancelId: null,
                error: '', notice: '', busy: false,
                init: function () {
                    try { var n = sessionStorage.getItem('stuParentsNotice'); if (n) { this.notice = n; sessionStorage.removeItem('stuParentsNotice'); } } catch (e) {}
                },
                run: function (fn, done) {
                    var self = this;
                    self.error = ''; self.notice = ''; self.busy = true;
                    return fn().then(function (data) { self.busy = false; done(data); }).catch(function (e) { self.busy = false; self.error = e.message; });
                },
                finish: function (msg) {
                    try { sessionStorage.setItem('stuParentsNotice', msg); } catch (e) {}
                    location.reload();
                },
                search: function () {
                    var self = this;
                    self.candidate = null;
                    if (!self.relationship) { self.error = 'Chọn mối quan hệ với phụ huynh.'; self.notice = ''; return; }
                    self.run(function () { return call('POST', cfg.search, { identifier: self.identifier }); }, function (d) { self.candidate = d.candidate; });
                },
                confirmLink: function () {
                    var self = this;
                    self.run(function () { return call('POST', cfg.store, { identifier: self.identifier, relationship: self.relationship }); }, function (d) { self.finish(d.message); });
                },
                respond: function (r, accept) {
                    var self = this;
                    self.run(function () { return call('POST', cfg.respond.replace('__ID__', r.id), { accept: accept }); }, function (d) { self.finish(d.message); });
                },
                unlink: function (l) {
                    var self = this;
                    self.run(function () { return call('DELETE', cfg.destroy.replace('__ID__', l.id)); }, function (d) { self.finish(d.message); });
                },
            };
        }
    </script>
@endsection

@extends('layouts.admin')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    {{-- SỬA 4/11 (khách: "admin có thể thông báo cho học sinh và phụ huynh và hiển thị ra thông báo") — soạn / gửi / thu hồi
         thông báo chung (Admin\AnnouncementController + Admin\SystemAnnouncementService). Người nhận thấy ở chuông, trang
         Thông báo của mình, dải "Thông báo mới" đầu trang Tổng quan; bản ghim còn hiện ở thanh "Thông báo hệ thống" trang chủ.
         CSS thường, tiền tố ann-. --}}
    @php
        $rows = $rows ?? [];
        $classes = $classes ?? [];
        $editing = $editing ?? null;
        $ready = $ready ?? true;
        $form = [
            'id' => $editing['id'] ?? null,
            'title' => old('title', $editing['title'] ?? ''),
            'body' => old('body', $editing['body'] ?? ''),
            'level' => old('level', $editing['level'] ?? 'normal'),
            'audiences' => array_values((array) old('audiences', $editing['audiences'] ?? ['students', 'parents'])),
            'scope' => old('scope', $editing['scope'] ?? 'all'),
            'classIds' => array_values(array_map('intval', (array) old('class_ids', $editing['classIds'] ?? []))),
            'pinHome' => (bool) old('pin_home', $editing['pinHome'] ?? false),
            'pinUntil' => old('pin_until', $editing['pinUntil'] ?? ''),
        ];
        $stats = [
            'sent' => count(array_filter($rows, fn ($r) => $r['state'] === 'published')),
            'draft' => count(array_filter($rows, fn ($r) => $r['state'] === 'draft')),
            'pinned' => count(array_filter($rows, fn ($r) => $r['pinnedNow'])),
        ];
        $payload = [
            'rows' => $rows,
            'classes' => $classes,
            'form' => $form,
            'previewUrl' => route('admin.announcements.preview'),
            'baseUrl' => url('admin/announcements'),
            'indexUrl' => route('admin.announcements.index'),
        ];
    @endphp

    @if (session('ann_status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('ann_status')])
    @endif

    <style>
        .ann-grid { display: grid; gap: 16px; }
        @media (min-width: 1200px) { .ann-grid { grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); align-items: start; } }
        .ann-card { border: 1px solid #DCE9F1; border-radius: 22px; background: #fff; box-shadow: 0 8px 26px rgba(18, 59, 104, .05); padding: 16px; }
        @media (min-width: 768px) { .ann-card { padding: 18px 20px; } }
        .ann-h { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 15px; font-weight: 800; color: #123B68; }
        .ann-h svg { width: 17px; height: 17px; color: #126F91; }
        .ann-sub { margin: 4px 0 0; font-size: 12.5px; line-height: 1.55; color: #5C7287; }
        .ann-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
        .ann-stat { border: 1px solid #DCE9F1; border-radius: 18px; background: #fff; padding: 12px 14px; }
        .ann-stat b { display: block; font-size: 22px; font-weight: 900; color: #123B68; line-height: 1.1; }
        .ann-stat span { font-size: 12px; font-weight: 700; color: #6B8196; }
        .ann-f { display: grid; gap: 12px; margin-top: 14px; }
        .ann-lb { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 800; color: #3D566B; }
        .ann-lb small { font-weight: 600; color: #94A3B8; }
        .ann-in, .ann-ta { width: 100%; font-family: inherit; border: 1px solid #D5E3EC; border-radius: 12px; padding: 9px 12px; font-size: 13.5px; color: #1E3A52; background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
        .ann-in:focus, .ann-ta:focus { border-color: #5AA9C8; box-shadow: 0 0 0 3px rgba(18, 111, 145, .12); }
        .ann-ta { min-height: 140px; resize: vertical; line-height: 1.6; }
        .ann-count { float: right; font-size: 11px; font-weight: 600; color: #94A3B8; }
        .ann-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .ann-chip { position: relative; display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 13px; border: 1px solid #D5E3EC; border-radius: 11px; background: #fff; font-size: 12.5px; font-weight: 700; color: #3D566B; cursor: pointer; user-select: none; transition: all .15s; }
        .ann-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .ann-chip svg { width: 15px; height: 15px; }
        .ann-chip.on { border-color: #126F91; background: #EAF5F8; color: #0D5B77; box-shadow: inset 0 0 0 1px #126F91; }
        .ann-chip.warn.on { border-color: #C27C1E; background: #FFF6E3; color: #8A5A12; box-shadow: inset 0 0 0 1px #C27C1E; }
        .ann-cls { margin-top: 8px; border: 1px solid #DCE9F1; border-radius: 14px; overflow: hidden; }
        .ann-cls-top { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px solid #EEF3F7; background: #F8FBFD; }
        .ann-cls-top .ann-in { height: 32px; padding: 0 10px; font-size: 12.5px; }
        .ann-cls-top button { flex: none; font-size: 11.5px; font-weight: 800; color: #126F91; }
        .ann-cls-list { max-height: 210px; overflow-y: auto; padding: 4px; }
        .ann-cls-it { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 9px; font-size: 12.5px; color: #33495C; cursor: pointer; }
        .ann-cls-it:hover { background: #F2F8FB; }
        .ann-cls-it input { width: 15px; height: 15px; accent-color: #126F91; }
        .ann-cls-it em { margin-left: auto; font-style: normal; font-size: 11px; color: #94A3B8; }
        .ann-pin { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; padding: 10px 12px; border: 1px dashed #CFE3EC; border-radius: 14px; background: #FAFDFE; }
        .ann-pin label { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: #33495C; cursor: pointer; }
        .ann-pin input[type=checkbox] { width: 16px; height: 16px; accent-color: #126F91; }
        .ann-pin input[type=date] { height: 32px; border: 1px solid #D5E3EC; border-radius: 10px; padding: 0 8px; font-size: 12.5px; }
        .ann-pin small { flex-basis: 100%; font-size: 11.5px; color: #7B8EA0; }
        .ann-reach { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 14px; background: #EAF5F8; font-size: 12.5px; color: #0D4F6A; }
        .ann-reach svg { width: 16px; height: 16px; flex: none; }
        .ann-reach.zero { background: #FFF4F2; color: #9F2D20; }
        .ann-acts { display: flex; flex-wrap: wrap; gap: 8px; }
        .ann-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border-radius: 12px; border: 1px solid #CFE3EC; background: #fff; color: #126F91; font-size: 12.5px; font-weight: 800; text-decoration: none; cursor: pointer; transition: all .15s; }
        .ann-btn:hover { background: #F0F8FB; }
        .ann-btn.pri { border-color: #126F91; background: #126F91; color: #fff; box-shadow: 0 8px 18px rgba(18, 111, 145, .22); }
        .ann-btn.pri:hover { background: #0D5B77; }
        .ann-btn[disabled] { opacity: .5; cursor: not-allowed; box-shadow: none; }
        .ann-btn svg { width: 15px; height: 15px; }
        .ann-err { margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: #FFF1EE; border: 1px solid #F6C9C0; color: #9F2D20; font-size: 12.5px; font-weight: 600; }
        .ann-editing { margin-top: 10px; padding: 8px 12px; border-radius: 12px; background: #FFF8E8; border: 1px solid #F2E1BC; font-size: 12.5px; color: #7A5212; }

        .ann-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
        .ann-tabs { display: inline-flex; padding: 3px; border-radius: 12px; background: #F1F6F9; }
        .ann-tab { height: 30px; padding: 0 11px; border-radius: 9px; font-size: 12px; font-weight: 800; color: #607A90; }
        .ann-tab.on { background: #fff; color: #123B68; box-shadow: 0 1px 4px rgba(18, 59, 104, .1); }
        .ann-tools .ann-in { flex: 1 1 160px; height: 34px; padding: 0 10px; font-size: 12.5px; }
        .ann-list { display: grid; gap: 10px; margin-top: 12px; }
        .ann-row { border: 1px solid #E3ECF2; border-radius: 16px; padding: 12px 14px; background: #fff; }
        .ann-row.is-draft { background: #FCFDFE; border-style: dashed; }
        .ann-row.is-rec { opacity: .72; }
        .ann-row-h { display: flex; align-items: flex-start; gap: 10px; }
        .ann-row-h h3 { flex: 1; min-width: 0; margin: 0; font-size: 13.5px; font-weight: 800; color: #123B68; line-height: 1.4; word-break: break-word; }
        .ann-tags { display: flex; flex-wrap: wrap; gap: 5px; flex: none; }
        .ann-tag { display: inline-flex; align-items: center; gap: 4px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; white-space: nowrap; }
        .ann-tag svg { width: 11px; height: 11px; }
        .t-sent { background: #E6F6EF; color: #1F7A55; }
        .t-draft { background: #FFF3DA; color: #93600F; }
        .t-rec { background: #F1F4F7; color: #6B7C8C; }
        .t-imp { background: #FDECC8; color: #8A5A12; }
        .t-pin { background: #E7F1FF; color: #2456A6; }
        .ann-row-m { margin-top: 5px; font-size: 11.5px; line-height: 1.55; color: #6B8196; }
        .ann-row-b { margin-top: 6px; font-size: 12.5px; line-height: 1.6; color: #3D566B; white-space: pre-line; word-break: break-word; }
        .ann-row-b.clip { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .ann-more { margin-top: 2px; font-size: 11.5px; font-weight: 800; color: #126F91; }
        .ann-row-a { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
        .ann-row-a .ann-btn { height: 30px; padding: 0 11px; border-radius: 10px; font-size: 11.5px; }
        .ann-row-a .ann-btn svg { width: 13px; height: 13px; }
        .ann-btn.danger { color: #B42318; border-color: #F3C7C1; }
        .ann-btn.danger:hover { background: #FFF3F1; }
        .ann-empty { padding: 26px 8px; text-align: center; font-size: 13px; color: #94A3B8; }
        .ann-pager { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; font-size: 12px; color: #607A90; }
        .ann-pager button { height: 30px; min-width: 30px; padding: 0 10px; border: 1px solid #DCE9F1; border-radius: 9px; background: #fff; font-weight: 800; color: #126F91; }
        .ann-pager button[disabled] { opacity: .4; cursor: not-allowed; }
    </style>

    <x-ws.page-header title="Thông báo" icon="megaphone"
                      subtitle="Gửi thông báo tới học sinh, phụ huynh, giáo viên — toàn hệ thống hoặc theo lớp. Người nhận thấy ngay ở chuông, trang Thông báo và trang Tổng quan." />

    <div x-data="annPage(@js($payload))" style="margin-top:16px">
        <div class="ann-stats">
            <div class="ann-stat"><b>{{ $stats['sent'] }}</b><span>Đã gửi</span></div>
            <div class="ann-stat"><b>{{ $stats['draft'] }}</b><span>Bản nháp</span></div>
            <div class="ann-stat"><b>{{ $stats['pinned'] }}</b><span>Đang ghim trang chủ</span></div>
        </div>

        <div class="ann-grid">
            {{-- ── Soạn thông báo ── --}}
            <section class="ann-card">
                <h2 class="ann-h"><x-lucide name="pen-line" />{{ $editing ? 'Viết tiếp bản nháp' : 'Soạn thông báo mới' }}</h2>
                <p class="ann-sub">Bấm <b>Gửi thông báo</b> là gửi ngay; chưa xong thì <b>Lưu nháp</b> để viết tiếp sau.</p>

                @if (! $ready)
                    <p class="ann-err">Chưa cập nhật cơ sở dữ liệu cho mục Thông báo. Hãy chạy <code>php artisan migrate</code> trên máy chủ.</p>
                @endif
                @if ($errors->any())
                    <p class="ann-err" role="alert">{{ $errors->first() }}</p>
                @endif
                @if ($editing)
                    <p class="ann-editing">Đang viết tiếp: <b>{{ $editing['title'] ?: 'Bản nháp chưa có tiêu đề' }}</b> · <a href="{{ route('admin.announcements.index') }}" style="font-weight:800;color:#126F91">Huỷ, soạn mới</a></p>
                @endif

                <form method="POST" action="{{ route('admin.announcements.store') }}" class="ann-f" novalidate @submit="onSubmit($event)">
                    @csrf
                    <template x-if="f.id"><input type="hidden" name="id" :value="f.id"></template>

                    <div>
                        <label class="ann-lb" for="ann-title">Tiêu đề <span class="ann-count" x-text="f.title.length + '/150'"></span></label>
                        <input id="ann-title" class="ann-in" type="text" name="title" maxlength="150" x-model="f.title" placeholder="Ví dụ: Lịch nghỉ lễ và lịch học bù tuần sau">
                    </div>

                    <div>
                        <label class="ann-lb" for="ann-body">Nội dung <span class="ann-count" x-text="f.body.length + '/5000'"></span></label>
                        <textarea id="ann-body" class="ann-ta" name="body" maxlength="5000" x-model="f.body" placeholder="Nhập nội dung thông báo… (xuống dòng thoải mái, người nhận đọc đủ nội dung khi bấm vào)"></textarea>
                    </div>

                    <div>
                        <span class="ann-lb">Gửi tới</span>
                        <div class="ann-chips">
                            @foreach ([['students', 'Học sinh', 'graduation-cap'], ['parents', 'Phụ huynh', 'users'], ['teachers', 'Giáo viên', 'presentation']] as [$audKey, $audLabel, $audIcon])
                                <label class="ann-chip" :class="{ on: f.audiences.includes('{{ $audKey }}') }">
                                    <input type="checkbox" name="audiences[]" value="{{ $audKey }}" x-model="f.audiences" @change="refresh()">
                                    <x-lucide :name="$audIcon" />{{ $audLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <span class="ann-lb">Phạm vi</span>
                        <div class="ann-chips">
                            <label class="ann-chip" :class="{ on: f.scope === 'all' }"><input type="radio" name="scope" value="all" x-model="f.scope" @change="refresh()"><x-lucide name="globe" />Toàn hệ thống</label>
                            <label class="ann-chip" :class="{ on: f.scope === 'classes' }"><input type="radio" name="scope" value="classes" x-model="f.scope" @change="refresh()"><x-lucide name="school" />Theo lớp</label>
                        </div>
                        {{-- Lớp đã chọn gửi qua ô ẩn (ô tick đang bị lọc bởi ô tìm vẫn được gửi đủ). --}}
                        <template x-for="cid in f.classIds" :key="'h' + cid"><input type="hidden" name="class_ids[]" :value="cid"></template>
                        <div class="ann-cls" x-show="f.scope === 'classes'" x-cloak>
                            <div class="ann-cls-top">
                                <input type="search" class="ann-in" x-model="clsQ" placeholder="Tìm lớp theo tên / mã…">
                                <span style="font-size:11.5px;color:#607A90;white-space:nowrap" x-text="'Đã chọn ' + f.classIds.length"></span>
                                <button type="button" x-show="f.classIds.length" @click="f.classIds = []; refresh()">Bỏ chọn</button>
                            </div>
                            <div class="ann-cls-list">
                                <template x-for="c in clsShown" :key="c.id">
                                    <label class="ann-cls-it">
                                        <input type="checkbox" :value="c.id" :checked="f.classIds.includes(c.id)" @change="toggleClass(c.id)">
                                        <span x-text="c.name"></span><em x-text="c.code"></em>
                                    </label>
                                </template>
                                <p class="ann-empty" style="padding:14px" x-show="!clsShown.length" x-text="classes.length ? 'Không có lớp khớp từ khoá.' : 'Chưa có lớp nào.'"></p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="ann-lb">Mức độ</span>
                        <div class="ann-chips">
                            <label class="ann-chip" :class="{ on: f.level === 'normal' }"><input type="radio" name="level" value="normal" x-model="f.level"><x-lucide name="message-square-text" />Thông thường</label>
                            <label class="ann-chip warn" :class="{ on: f.level === 'important' }"><input type="radio" name="level" value="important" x-model="f.level"><x-lucide name="alert-triangle" />Quan trọng</label>
                        </div>
                    </div>

                    <div class="ann-pin">
                        <label><input type="checkbox" name="pin_home" value="1" x-model="f.pinHome">Ghim lên thanh “Thông báo hệ thống” ở trang chủ</label>
                        <label x-show="f.pinHome" x-cloak>đến hết ngày <input type="date" name="pin_until" x-model="f.pinUntil" :min="today"></label>
                        <small>Ghim thì <b>mọi người vào trang chủ đều thấy</b> (kể cả chưa đăng nhập) — chỉ ghim nội dung công khai được. Để trống ngày = ghim đến khi bỏ ghim.</small>
                    </div>

                    <div class="ann-reach" :class="{ zero: reach && reach.total === 0 }">
                        <x-lucide name="send" />
                        <span x-text="reachText"></span>
                    </div>

                    <div class="ann-acts">
                        <button type="submit" name="action" value="publish" class="ann-btn pri" :disabled="!canSend"><x-lucide name="send" />Gửi thông báo</button>
                        <button type="submit" name="action" value="draft" class="ann-btn"><x-lucide name="save" />Lưu nháp</button>
                    </div>
                </form>
            </section>

            {{-- ── Lịch sử ── --}}
            <section class="ann-card">
                <h2 class="ann-h"><x-lucide name="history" />Thông báo đã tạo</h2>
                <p class="ann-sub">Thông báo đã gửi không sửa được — gửi nhầm thì bấm <b>Thu hồi</b> (gỡ khỏi hộp thư người nhận) rồi tạo thông báo mới.</p>

                <div class="ann-tools">
                    <div class="ann-tabs" role="tablist">
                        <template x-for="t in tabs" :key="t[0]">
                            <button type="button" class="ann-tab" :class="{ on: filter === t[0] }" @click="filter = t[0]; page = 1" x-text="t[1] + ' (' + countOf(t[0]) + ')'"></button>
                        </template>
                    </div>
                    <input type="search" class="ann-in" x-model="q" @input="page = 1" placeholder="Tìm theo tiêu đề / nội dung…">
                </div>

                <div class="ann-list">
                    <template x-for="r in visible" :key="r.id">
                        <article class="ann-row" :class="{ 'is-draft': r.state === 'draft', 'is-rec': r.state === 'recalled' }">
                            <div class="ann-row-h">
                                <h3 x-text="r.title || 'Bản nháp chưa có tiêu đề'"></h3>
                                <div class="ann-tags">
                                    <span class="ann-tag t-imp" x-show="r.level === 'important'">Quan trọng</span>
                                    <span class="ann-tag t-pin" x-show="r.pinnedNow"><x-lucide name="bookmark" />Ghim</span>
                                    <span class="ann-tag" :class="r.state === 'published' ? 't-sent' : (r.state === 'draft' ? 't-draft' : 't-rec')"
                                          x-text="r.state === 'published' ? 'Đã gửi' : (r.state === 'draft' ? 'Bản nháp' : 'Đã thu hồi')"></span>
                                </div>
                            </div>
                            <p class="ann-row-m" x-text="metaText(r)"></p>
                            <p class="ann-row-m" x-show="r.state !== 'draft'" x-text="sentText(r)"></p>
                            <p class="ann-row-b" :class="{ clip: open !== r.id }" x-text="r.body || 'Chưa nhập nội dung.'"></p>
                            <button type="button" class="ann-more" x-show="(r.body || '').length > 140" @click="open = open === r.id ? null : r.id" x-text="open === r.id ? 'Thu gọn' : 'Xem đầy đủ'"></button>

                            <div class="ann-row-a">
                                <template x-if="r.state === 'draft'">
                                    <a class="ann-btn" :href="indexUrl + '?edit=' + r.id"><x-lucide name="pencil" />Viết tiếp</a>
                                </template>
                                <template x-if="r.state === 'draft'">
                                    <form method="POST" :action="baseUrl + '/' + r.id" @submit="if (!confirm('Xoá bản nháp này?')) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ann-btn danger"><x-lucide name="trash-2" />Xoá nháp</button>
                                    </form>
                                </template>
                                <template x-if="r.state === 'published'">
                                    <form method="POST" :action="baseUrl + '/' + r.id + '/pin'">
                                        @csrf
                                        <button type="submit" class="ann-btn"><x-lucide name="bookmark" /><span x-text="r.pinnedNow ? 'Bỏ ghim trang chủ' : 'Ghim trang chủ'"></span></button>
                                    </form>
                                </template>
                                <template x-if="r.state === 'published'">
                                    <form method="POST" :action="baseUrl + '/' + r.id + '/recall'" @submit="if (!confirm('Thu hồi thông báo này? Thông báo sẽ bị gỡ khỏi hộp thư của tất cả người nhận.')) $event.preventDefault()">
                                        @csrf
                                        <button type="submit" class="ann-btn danger"><x-lucide name="rotate-ccw" />Thu hồi</button>
                                    </form>
                                </template>
                                <template x-if="r.state !== 'draft'">
                                    <button type="button" class="ann-btn" @click="reuse(r)"><x-lucide name="copy" />Dùng lại nội dung</button>
                                </template>
                            </div>
                        </article>
                    </template>
                    <p class="ann-empty" x-show="!filtered.length" x-text="rows.length ? 'Không có thông báo khớp bộ lọc.' : 'Chưa có thông báo nào. Soạn thông báo đầu tiên ở khung bên cạnh.'"></p>
                </div>

                <div class="ann-pager" x-show="pages > 1" x-cloak>
                    <button type="button" :disabled="page <= 1" @click="page--">‹</button>
                    <span x-text="'Trang ' + page + ' / ' + pages"></span>
                    <button type="button" :disabled="page >= pages" @click="page++">›</button>
                </div>
            </section>
        </div>
    </div>

    <script>
        function annPage(d) {
            return {
                rows: d.rows, classes: d.classes, f: d.form, previewUrl: d.previewUrl, baseUrl: d.baseUrl, indexUrl: d.indexUrl,
                clsQ: '', q: '', filter: 'all', page: 1, size: 8, open: null, reach: null, loading: false, timer: null, seq: 0,
                tabs: [['all', 'Tất cả'], ['published', 'Đã gửi'], ['draft', 'Nháp'], ['recalled', 'Thu hồi']],
                today: new Date().toISOString().slice(0, 10),
                init() { this.f.classIds = (this.f.classIds || []).map(Number); this.refresh(); },
                get clsShown() {
                    var q = this.clsQ.trim().toLowerCase();
                    return !q ? this.classes : this.classes.filter(function (c) { return (c.name + ' ' + c.code).toLowerCase().indexOf(q) !== -1; });
                },
                toggleClass(id) {
                    var i = this.f.classIds.indexOf(id);
                    if (i === -1) this.f.classIds.push(id); else this.f.classIds.splice(i, 1);
                    this.refresh();
                },
                refresh() {
                    var self = this;
                    clearTimeout(this.timer);
                    if (!this.f.audiences.length || (this.f.scope === 'classes' && !this.f.classIds.length)) { this.reach = null; return; }
                    this.loading = true;
                    this.timer = setTimeout(function () {
                        var my = ++self.seq;
                        var url = self.previewUrl + '?audiences=' + encodeURIComponent(self.f.audiences.join(',')) + '&scope=' + self.f.scope + '&classes=' + encodeURIComponent(self.f.classIds.join(','));
                        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (j) { if (my === self.seq) { self.reach = j; self.loading = false; } })
                            .catch(function () { if (my === self.seq) { self.reach = null; self.loading = false; } });
                    }, 250);
                },
                get reachText() {
                    if (!this.f.audiences.length) return 'Chọn ít nhất một nhóm người nhận.';
                    if (this.f.scope === 'classes' && !this.f.classIds.length) return 'Chọn lớp nhận thông báo.';
                    if (this.loading || !this.reach) return 'Đang đếm số người nhận…';
                    var r = this.reach, parts = [];
                    if (this.f.audiences.includes('students')) parts.push(r.students + ' học sinh');
                    if (this.f.audiences.includes('parents')) parts.push(r.parents + ' phụ huynh');
                    if (this.f.audiences.includes('teachers')) parts.push(r.teachers + ' giáo viên');
                    if (r.total === 0) return 'Không có tài khoản nào thuộc nhóm / lớp đã chọn — chưa gửi được.';
                    return 'Sẽ gửi tới ' + parts.join(' · ') + (r.total !== r.students + r.parents + r.teachers ? ' (' + r.total + ' tài khoản, đã bỏ trùng)' : '') + '.';
                },
                get canSend() {
                    return this.f.title.trim() !== '' && this.f.body.trim() !== '' && this.f.audiences.length > 0
                        && !(this.f.scope === 'classes' && !this.f.classIds.length) && !(this.reach && this.reach.total === 0);
                },
                onSubmit(e) {
                    var btn = e.submitter;
                    if (btn && btn.value === 'publish') {
                        var n = this.reach ? this.reach.total : null;
                        if (!confirm('Gửi thông báo' + (n !== null ? ' tới ' + n + ' người nhận' : '') + '? Đã gửi thì không sửa được (chỉ thu hồi được).')) e.preventDefault();
                    }
                },
                reuse(r) {
                    this.f.id = null; this.f.title = r.title; this.f.body = r.body; this.f.level = r.level;
                    this.f.audiences = r.audiences.slice(); this.f.scope = r.scope; this.f.classIds = r.classIds.slice();
                    this.f.pinHome = false; this.f.pinUntil = '';
                    this.refresh();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                countOf(k) { return k === 'all' ? this.rows.length : this.rows.filter(function (r) { return r.state === k; }).length; },
                get filtered() {
                    var k = this.filter, q = this.q.trim().toLowerCase();
                    return this.rows.filter(function (r) {
                        return (k === 'all' || r.state === k) && (!q || ((r.title || '') + ' ' + (r.body || '')).toLowerCase().indexOf(q) !== -1);
                    });
                },
                get pages() { return Math.max(1, Math.ceil(this.filtered.length / this.size)); },
                get visible() { if (this.page > this.pages) this.page = this.pages; return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
                metaText(r) {
                    var when = r.state === 'draft' ? 'Cập nhật ' + r.updatedAt : (r.state === 'recalled' ? 'Thu hồi ' + r.recalledAt : 'Gửi ' + r.publishedAt);
                    return r.audienceLabel + ' · ' + (r.scope === 'classes' ? 'Lớp: ' + (r.classLabel || '—') : 'Toàn hệ thống') + ' · ' + when + ' · ' + r.author;
                },
                sentText(r) {
                    var p = [];
                    if (r.audiences.includes('students')) p.push(r.students + ' học sinh');
                    if (r.audiences.includes('parents')) p.push(r.parents + ' phụ huynh');
                    if (r.audiences.includes('teachers')) p.push(r.teachers + ' giáo viên');
                    return 'Đã gửi tới ' + (p.join(' · ') || '0 người') + (r.pinnedNow && r.pinUntil ? ' · ghim đến hết ' + r.pinUntil.split('-').reverse().join('/') : '');
                },
            };
        }
    </script>
@endsection

{{-- ═══════════════ THÔNG TIN CÁ NHÂN (dùng chung 4 khu) ═══════════════
     SỬA 9/10 (khách: "copy UI màn thông tin cá nhân qua màn hồ sơ, nhớ cho update avatar") —
     chép từ education-main/src/components/PersonalProfile.jsx: tiêu đề + nhãn vai trò, thẻ Ảnh đại diện
     (chọn ảnh -> căn chỉnh phóng to/vị trí -> "Dùng ảnh này" -> chỉ lưu khi bấm Lưu), thẻ Thông tin cơ
     bản, khối Thông tin tài khoản, thanh lưu có Hủy.

     Khác source (cố ý): source lưu vào localStorage trình duyệt, ở đây lưu THẬT lên máy chủ
     (users.avatar_path) qua App\Services\Account\AvatarService. Email là tên đăng nhập nên chỉ xem.
     Không có ô "Giới thiệu ngắn" vì bảng users không có cột này (giáo viên đã có "Hồ sơ chuyên môn" riêng).

     Tham số:
       $user          người dùng đang xem
       $action        URL PUT lưu hồ sơ
       $roleLabel     nhãn vai trò hiện ở góc phải tiêu đề
       $withLocation  (mặc định true) có ô Tỉnh/thành + Khu vực không — admin không có
       $profileUrl, $passwordUrl  cho thanh chọn Thông tin cá nhân / Đổi mật khẩu --}}
@php
    $withLocation = $withLocation ?? true;
    $avatarUrl = $user->avatarUrl();
    $initial = mb_strtoupper(mb_substr(trim((string) ($user->name ?? '')) ?: '?', 0, 1));
    $statusLabel = ($user->status ?? 'active') === 'active' ? 'Đang hoạt động' : 'Đã khóa';
    $regionOptions = $withLocation ? \App\Support\VietnamProvinces::regionOptions() : [];
    $provinceOptions = $withLocation ? \App\Support\VietnamProvinces::options() : [];
@endphp

@include('partials.personal-profile-style')

{{-- Báo MỌI lỗi kiểm tra (kể cả lỗi không gắn với ô nào, vd ảnh quá lớn/sai định dạng) để không bị nuốt mất. --}}
@if ($errors->any())
    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
@endif

<section class="personal-profile" aria-labelledby="personal-profile-title" data-pp-root>
    @include('partials.account-tabs', ['active' => 'profile'])

    <header class="pp-heading">
        <div>
            <p>Hồ sơ của bạn</p>
            <h1 id="personal-profile-title">Thông tin cá nhân</h1>
            <span>Cập nhật thông tin liên hệ và ảnh đại diện của bạn.</span>
        </div>
        <span class="pp-role"><x-lucide name="user-round" class="h-4 w-4" />{{ $roleLabel }}</span>
    </header>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate data-pp-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="remove_avatar" value="0" data-pp-remove>
        {{-- Ô này nhận ẢNH ĐÃ CẮT (JS gán vào sau khi bấm "Dùng ảnh này"); không có JS thì không gửi gì. --}}
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="pp-file" tabindex="-1" aria-hidden="true" data-pp-avatar>

        <div class="pp-layout">
            <aside class="pp-card pp-avatar-card">
                <h2><x-lucide name="camera" class="h-[18px] w-[18px]" />Ảnh đại diện</h2>
                <div class="pp-avatar-preview">
                    <img src="{{ $avatarUrl }}" alt="Ảnh đại diện xem trước" data-pp-img @unless ($avatarUrl) hidden @endunless>
                    <div class="pp-initials" data-pp-initial @if ($avatarUrl) hidden @endif aria-hidden="true">{{ $initial }}</div>
                </div>
                <strong data-pp-name>{{ old('name', $user->name) ?: 'Họ tên của bạn' }}</strong>
                <span class="pp-account">{{ $user->email }}</span>

                <input type="file" accept="image/jpeg,image/png,image/webp" aria-label="Chọn ảnh đại diện" class="pp-file" id="profile-avatar" data-pp-pick>
                <button type="button" data-pp-choose><x-lucide name="image" class="h-[17px] w-[17px]" />Chọn ảnh mới</button>
                <button type="button" class="pp-text-button" data-pp-default @if (! $avatarUrl) disabled @endif>Dùng ảnh mặc định</button>
                <p class="pp-hint">JPG, PNG hoặc WebP · Tối đa 5 MB.<br>Ảnh chỉ thay đổi sau khi bạn lưu hồ sơ.</p>
                <p class="pp-error" role="alert" data-pp-avatar-error hidden style="width:100%;margin-top:0"></p>
                @error('avatar')<p class="pp-error" role="alert" style="width:100%;margin-top:0">{{ $message }}</p>@enderror

                <section class="pp-crop" aria-label="Căn chỉnh ảnh đại diện" data-pp-crop hidden>
                    <h3>Căn chỉnh ảnh</h3>
                    <canvas width="512" height="512" aria-label="Xem trước ảnh đã cắt" data-pp-canvas></canvas>
                    <label>Phóng to<input type="range" min="1" max="3" step="0.05" value="1" data-pp-zoom></label>
                    <label>Vị trí ngang<input type="range" min="0" max="100" step="1" value="50" data-pp-x></label>
                    <label>Vị trí dọc<input type="range" min="0" max="100" step="1" value="50" data-pp-y></label>
                    <div class="pp-crop-actions">
                        <button type="button" data-pp-crop-cancel>Hủy chọn ảnh</button>
                        <button type="button" class="pp-primary" data-pp-crop-apply>Dùng ảnh này</button>
                    </div>
                </section>
            </aside>

            <div class="pp-card pp-form-card">
                <h2>Thông tin cơ bản</h2>
                <p class="pp-hint">Họ tên là thông tin bắt buộc. Các mục còn lại có thể bổ sung sau.</p>
                <div class="pp-fields">
                    <label for="profile-name">Họ và tên<span class="pp-req" aria-hidden="true"> *</span>
                        <input id="profile-name" name="name" type="text" autocomplete="name" required maxlength="255"
                               value="{{ old('name', $user->name) }}" placeholder="Nhập họ và tên" data-pp-field data-saved="{{ $user->name }}"
                               @error('name') aria-invalid="true" @enderror>
                        @error('name')<small class="pp-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label for="profile-phone">Số điện thoại
                        <input id="profile-phone" name="phone" type="tel" autocomplete="tel" maxlength="32"
                               value="{{ old('phone', $user->phone) }}" placeholder="Chưa cập nhật" data-pp-field data-saved="{{ $user->phone }}"
                               @error('phone') aria-invalid="true" @enderror>
                        @error('phone')<small class="pp-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="pp-wide" for="profile-email">Email đăng nhập
                        <input id="profile-email" type="email" value="{{ $user->email }}" disabled>
                        <small class="pp-hint">Email là tên đăng nhập nên không tự đổi ở đây. Cần đổi email, hãy liên hệ quản trị viên.</small>
                    </label>
                    @if ($withLocation)
                        <label for="province">Tỉnh/thành
                            <span class="pp-select"><x-ws.select id="province" name="province" data-pp-field data-saved="{{ $user->province }}">
                                <option value="">— Chưa chọn —</option>
                                @foreach ($provinceOptions as $p)
                                    <option value="{{ $p }}" @selected(old('province', $user->province ?? '') === $p)>{{ $p }}</option>
                                @endforeach
                            </x-ws.select></span>
                            @error('province')<small class="pp-field-error">{{ $message }}</small>@enderror
                        </label>
                        <label for="region">Khu vực
                            <span class="pp-select"><x-ws.select id="region" name="region" data-pp-field data-saved="{{ $user->region }}">
                                <option value="">— Chưa chọn —</option>
                                @foreach ($regionOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('region', $user->region ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select></span>
                            @error('region')<small class="pp-field-error">{{ $message }}</small>@enderror
                        </label>
                    @endif
                </div>

                <div class="pp-account-info">
                    <h3><x-lucide name="shield-check" class="h-[17px] w-[17px]" />Thông tin tài khoản</h3>
                    <dl>
                        <div><dt>Tên đăng nhập</dt><dd>{{ $user->email }}</dd></div>
                        <div><dt>Vai trò</dt><dd>{{ $roleLabel }}</dd></div>
                        <div><dt>Trạng thái</dt><dd>{{ $statusLabel }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>

        <footer class="pp-save-bar">
            <div>
                <strong data-pp-state>Thông tin đã được cập nhật</strong>
                <p>Thay đổi chỉ có hiệu lực sau khi bạn bấm Lưu.</p>
            </div>
            <div>
                <button type="button" data-pp-cancel disabled>Hủy</button>
                <button type="submit" class="pp-primary" data-pp-save><x-lucide name="save" class="h-[17px] w-[17px]" />Lưu thay đổi</button>
            </div>
        </footer>
    </form>
</section>

<script>
    (function () {
        var root = document.querySelector('[data-pp-root]');
        if (!root) return;
        var form = root.querySelector('[data-pp-form]');
        var q = function (s) { return root.querySelector(s); };
        var img = q('[data-pp-img]'), initial = q('[data-pp-initial]'), nameEl = q('[data-pp-name]');
        var avatarInput = q('[data-pp-avatar]'), removeInput = q('[data-pp-remove]'), pick = q('[data-pp-pick]');
        var chooseBtn = q('[data-pp-choose]'), defaultBtn = q('[data-pp-default]');
        var cropBox = q('[data-pp-crop]'), canvas = q('[data-pp-canvas]');
        var zoom = q('[data-pp-zoom]'), px = q('[data-pp-x]'), py = q('[data-pp-y]');
        var errBox = q('[data-pp-avatar-error]'), stateEl = q('[data-pp-state]');
        var saveBtn = q('[data-pp-save]'), cancelBtn = q('[data-pp-cancel]');
        var fields = [].slice.call(root.querySelectorAll('[data-pp-field]'));
        var savedAvatar = img.getAttribute('src') || '';
        var hadAvatar = savedAvatar !== '';
        var initialValues = fields.map(function (f) { return f.getAttribute('data-saved') || ''; });
        var source = null, previewUrl = null, busy = false, submitting = false;
        var TYPES = ['image/jpeg', 'image/png', 'image/webp'];

        function showError(msg) { errBox.textContent = msg || ''; errBox.hidden = !msg; }
        function avatarChanged() { return removeInput.value === '1' || (avatarInput.files && avatarInput.files.length > 0); }
        function dirty() {
            return fields.some(function (f, i) { return f.value !== initialValues[i]; }) || avatarChanged() || !!source;
        }
        function refresh() {
            var d = dirty();
            saveBtn.disabled = !d || !!source || busy;
            cancelBtn.disabled = !d || busy;
            stateEl.textContent = busy ? 'Đang xử lý…' : source ? 'Hãy hoàn tất căn chỉnh ảnh' : d ? 'Bạn có thay đổi chưa lưu' : 'Thông tin đã được cập nhật';
            var hasImg = !img.hidden;
            defaultBtn.disabled = !(hasImg || hadAvatar) || (removeInput.value === '1');
        }
        function setPreview(url) {
            if (url) { img.src = url; img.hidden = false; initial.hidden = true; }
            else { img.removeAttribute('src'); img.hidden = true; initial.hidden = false; }
        }
        function clearPreviewUrl() { if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; } }
        function clearSource() {
            if (source && source.url) URL.revokeObjectURL(source.url);
            source = null; cropBox.hidden = true;
        }
        function draw() {
            if (!source) return;
            var im = source.image, side = Math.min(im.naturalWidth, im.naturalHeight) / Number(zoom.value);
            var sx = (im.naturalWidth - side) * Number(px.value) / 100, sy = (im.naturalHeight - side) * Number(py.value) / 100;
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(im, sx, sy, side, side, 0, 0, canvas.width, canvas.height);
        }

        chooseBtn.addEventListener('click', function () { pick.click(); });
        pick.addEventListener('change', function () {
            var file = pick.files && pick.files[0];
            pick.value = '';
            if (!file) return;
            showError('');
            if (TYPES.indexOf(file.type) === -1) { showError('Chọn ảnh JPG, PNG hoặc WebP.'); return; }
            if (!file.size || file.size > 5 * 1024 * 1024) { showError('Ảnh phải nhỏ hơn hoặc bằng 5 MB.'); return; }
            busy = true; refresh();
            var url = URL.createObjectURL(file), im = new Image();
            im.onload = function () {
                busy = false;
                if (!im.naturalWidth || !im.naturalHeight || im.naturalWidth * im.naturalHeight > 40000000) {
                    URL.revokeObjectURL(url); showError('Ảnh quá lớn. Hãy chọn ảnh có tối đa 40 triệu điểm ảnh.'); refresh(); return;
                }
                clearSource();
                zoom.value = 1; px.value = 50; py.value = 50;
                source = { image: im, url: url };
                cropBox.hidden = false; draw(); refresh();
            };
            im.onerror = function () { busy = false; URL.revokeObjectURL(url); showError('Không đọc được ảnh. Hãy chọn một tệp ảnh khác.'); refresh(); };
            im.src = url;
        });
        [zoom, px, py].forEach(function (el) { el.addEventListener('input', draw); });
        q('[data-pp-crop-cancel]').addEventListener('click', function () { clearSource(); showError(''); refresh(); });
        q('[data-pp-crop-apply]').addEventListener('click', function () {
            if (!source) return;
            busy = true; refresh();
            canvas.toBlob(function (blob) {
                busy = false;
                if (!blob) { showError('Không xử lý được ảnh. Hãy chọn lại ảnh.'); refresh(); return; }
                try {
                    var ext = blob.type === 'image/webp' ? 'webp' : blob.type === 'image/jpeg' ? 'jpg' : 'png';
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], 'avatar.' + ext, { type: blob.type }));
                    avatarInput.files = dt.files;
                } catch (e) { showError('Trình duyệt chưa hỗ trợ cắt ảnh. Hãy dùng trình duyệt mới hơn.'); refresh(); return; }
                clearPreviewUrl();
                previewUrl = URL.createObjectURL(blob);
                setPreview(previewUrl);
                removeInput.value = '0';
                clearSource(); showError(''); refresh();
            }, 'image/webp', 0.88);
        });
        defaultBtn.addEventListener('click', function () {
            clearSource(); clearPreviewUrl();
            try { avatarInput.value = ''; } catch (e) {}
            removeInput.value = hadAvatar ? '1' : '0';
            setPreview(null); showError(''); refresh();
        });
        cancelBtn.addEventListener('click', function () {
            clearSource(); clearPreviewUrl();
            try { avatarInput.value = ''; } catch (e) {}
            removeInput.value = '0';
            fields.forEach(function (f, i) { f.value = initialValues[i]; });
            nameEl.textContent = initialValues[0] || 'Họ tên của bạn';
            setPreview(hadAvatar ? savedAvatar : null); showError(''); refresh();
        });
        fields.forEach(function (f) {
            f.addEventListener('input', function () { if (f.name === 'name') nameEl.textContent = f.value.trim() || 'Họ tên của bạn'; refresh(); });
            f.addEventListener('change', refresh);
        });
        form.addEventListener('submit', function (e) {
            if (busy || source) { e.preventDefault(); return; }
            var name = form.querySelector('[name="name"]');
            if (!name.value.trim()) { e.preventDefault(); name.setAttribute('aria-invalid', 'true'); name.focus(); return; }
            submitting = true;
        });
        window.addEventListener('beforeunload', function (e) { if (dirty() && !submitting) { e.preventDefault(); e.returnValue = ''; } });
        refresh();
    })();
</script>

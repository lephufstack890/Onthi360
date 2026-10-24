{{-- SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới, thiếu field nào thì bổ sung cả admin") — các trường hiển thị
     ở trang Cuộc thi & phòng thi mới. Dùng chung cho form Tạo / Sửa. $competition = null khi tạo mới.
     Cần migration 001260; chưa chạy thì khối này tự ẩn (Competition::supportsHubFields()). --}}
@php
    $hubCompetition = $competition ?? null;
    $learningPathOptions = $learningPathOptions ?? [];
    $hasPassword = $hubCompetition?->hasEntryPassword() ?? false;
@endphp
@if (\App\Models\Competition::supportsHubFields())
    <div class="rounded-xl border border-sky-100 bg-sky-50/40 p-4 space-y-3">
        <p class="text-[13px] font-medium text-sky-800">Hiển thị ở trang Cuộc thi &amp; phòng thi</p>
        <div>
            <label class="block text-xs text-slate-500 mb-1" for="description">Mô tả ngắn (hiện ở thẻ cuộc thi)</label>
            <textarea id="description" name="description" rows="2" maxlength="1000" class="admin-input"
                      placeholder="VD: Thử sức với thuật toán, rèn tư duy giải quyết vấn đề…">{{ old('description', $hubCompetition?->description) }}</textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-slate-500 mb-1" for="edition_label">Nhãn mùa giải / đối tượng</label>
                <input id="edition_label" name="edition_label" type="text" maxlength="120" class="admin-input"
                       value="{{ old('edition_label', $hubCompetition?->edition_label) }}" placeholder="VD: Mùa giải 2026 · Dành cho học sinh THCS">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1" for="learning_path_id">Lộ trình học liên quan</label>
                <x-ws.select id="learning_path_id" name="learning_path_id">
                    <option value="">— Không gắn lộ trình —</option>
                    @foreach ($learningPathOptions as $lp)
                        <option value="{{ $lp['id'] }}" @selected((string) old('learning_path_id', $hubCompetition?->learning_path_id) === (string) $lp['id'])>{{ $lp['title'] }}</option>
                    @endforeach
                </x-ws.select>
            </div>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1" for="image">Ảnh bìa (JPG/PNG/WebP, tối đa 4MB — để trống dùng ảnh mặc định)</label>
            <div class="flex items-center gap-3 flex-wrap">
                @if ($hubCompetition)
                    <img src="{{ $hubCompetition->imageUrl() }}" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:10px;border:1px solid #e0ecf5">
                @endif
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="text-xs text-slate-600">
                @if ($hubCompetition?->image_path)
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-500"><input type="checkbox" name="remove_image" value="1"> Bỏ ảnh đang dùng</label>
                @endif
            </div>
        </div>
        <div x-show="type === 'survey'" x-cloak class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
            <label class="block text-xs font-medium text-emerald-800 mb-1" for="entry_password">Mật khẩu tham gia khảo sát {{ $hasPassword ? '' : '(bắt buộc)' }}</label>
            <input id="entry_password" name="entry_password" type="text" maxlength="100" autocomplete="off" class="admin-input"
                   placeholder="{{ $hasPassword ? 'Để trống = giữ mật khẩu hiện tại' : 'VD: KS2026' }}" value="{{ old('entry_password') }}">
            <p class="text-xs text-emerald-700/80 mt-1">Học sinh nhập mật khẩu này mỗi khi bắt đầu / tiếp tục khảo sát (không cần đăng ký, không chờ duyệt). Mật khẩu hiện tại xem ở trang chi tiết cuộc thi.</p>
            @if ($hasPassword)
                <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-slate-500"><input type="checkbox" name="clear_entry_password" value="1"> Xoá mật khẩu (học sinh vào thẳng, không cần nhập)</label>
            @endif
        </div>
        <p x-show="type !== 'survey'" class="text-xs text-slate-400">Cuộc thi: học sinh bấm "Đăng ký tham gia" → giáo viên tổ chức / admin duyệt (hoặc thêm thẳng học sinh trong phòng thi) → mỗi kỳ nộp 1 lần.</p>
    </div>
@endif

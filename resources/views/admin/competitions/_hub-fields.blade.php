{{-- SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới, thiếu field nào thì bổ sung cả admin") — các trường hiển thị
     ở trang Cuộc thi & phòng thi mới. Dùng chung cho form Tạo / Sửa. $competition = null khi tạo mới.
     Cần migration 001260; chưa chạy thì khối này tự ẩn (Competition::supportsHubFields()). --}}
@php
    $hubCompetition = $competition ?? null;
    $courseOptions = $courseOptions ?? [];
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
                {{-- SỬA 24/10 (khách: "cuộc thi / khảo sát thuộc khoá học nào — lộ trình học = khoá học") --}}
                <label class="block text-xs text-slate-500 mb-1" for="course_id">Lộ trình học (khoá học của cuộc thi)</label>
                <x-ws.select id="course_id" name="course_id">
                    <option value="">— Không thuộc khoá học nào —</option>
                    @foreach ($courseOptions as $co)
                        <option value="{{ $co['id'] }}" @selected((string) old('course_id', $hubCompetition?->course_id) === (string) $co['id'])>{{ $co['title'] }}</option>
                    @endforeach
                </x-ws.select>
                @unless (\App\Models\Competition::supportsCourse())
                    <p class="text-xs text-rose-600 mt-1">Chưa chạy migration 001270 — chạy "php artisan migrate" để chọn khoá học.</p>
                @endunless
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

            {{-- SỬA 24/10 (khách: "chọn hình ảnh từ catalog mặc định như chỗ tài liệu") — bấm chọn là xong. Tải ảnh riêng ở
                 ô trên thì ảnh tải lên được ưu tiên. Không chọn gì = giữ ảnh hiện tại. --}}
            @php $ccPicked = (string) old('cover_catalog', \App\Support\CompetitionCover::idOf($hubCompetition?->image_path) ?? ''); @endphp
            <div class="mt-3" x-data="{ picked: @js($ccPicked) }">
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                    <span class="block text-xs text-slate-500">Hoặc chọn ảnh bìa từ catalog</span>
                    <button type="button" class="ccv-clear" x-show="picked !== ''" x-cloak @click="picked = ''">Bỏ chọn</button>
                </div>
                <div class="ccv-grid" role="radiogroup" aria-label="Ảnh bìa cuộc thi từ catalog">
                    @foreach (\App\Support\CompetitionCover::options() as $cc)
                        <label class="ccv-item" :class="{ 'is-on': picked === @js($cc['id']) }">
                            <input type="radio" name="cover_catalog" value="{{ $cc['id'] }}" x-model="picked">
                            <img src="{{ $cc['url'] }}" alt="" loading="lazy">
                            <span>{{ $cc['title'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <style>
            .ccv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
            .ccv-item { position: relative; display: flex; min-width: 0; flex-direction: column; gap: 6px; box-sizing: border-box; border: 2px solid transparent; border-radius: 12px; background: #fff; padding: 7px; cursor: pointer; }
            .ccv-item:hover { border-color: #bfdbfe; }
            .ccv-item.is-on { border-color: #2563eb; box-shadow: 0 0 0 2px #dbeafe; }
            .ccv-item input { position: absolute; right: 12px; top: 12px; width: 16px; height: 16px; margin: 0; accent-color: #2563eb; }
            .ccv-item img { display: block; width: 100%; aspect-ratio: 16 / 9; border-radius: 8px; object-fit: cover; background: #f8fbfc; }
            .ccv-item span { font-size: 11px; line-height: 1.35; font-weight: 600; color: #475569; }
            .ccv-clear { border: 0; background: transparent; padding: 2px 6px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #126f91; cursor: pointer; }
            .ccv-clear:hover { background: #eaf5f8; }
        </style>
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

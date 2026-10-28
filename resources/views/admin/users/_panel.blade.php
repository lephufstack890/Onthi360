{{-- Nội dung popup "Chi tiết người dùng" (Admin > Người dùng): 3 tab Thông tin / Phân quyền / Lịch sử.
     Được nạp bằng fetch từ route admin.users.panel (UserController::panel) vào #aumBody của partials.admin-users-modal.
     Mọi thao tác lưu gọi lại các route PUT/DELETE sẵn có (kèm Accept: application/json) rồi nạp lại chính partial này. --}}
@php
    $isTrashed = $trashed;
    $isSuspended = ! $isTrashed && $u->status === 'suspended';
    $stateLabel = $isTrashed ? 'Đã xóa' : ($isSuspended ? 'Tạm khóa' : 'Hoạt động');
    $stateClass = ($isTrashed || $isSuspended) ? 'aum-state--off' : 'aum-state--ok';
    $stateLabelFor = fn ($s) => $s === 'suspended' ? 'Tạm khóa' : 'Hoạt động';
    $matrixOn = ($matrix['mode'] ?? '') === 'matrix';
    $readonlyPerm = $matrix['readonly'] ?? false;
    $tagText = ['revoked' => 'Thu hồi', 'extra' => 'Cấp thêm'];
@endphp

<div class="aum-panel" data-user="{{ $u->id }}" data-name="{{ $u->name }}" data-trashed="{{ $isTrashed ? 1 : 0 }}" data-self="{{ $isSelf ? 1 : 0 }}">
    <header class="aum-id">
        <x-ws.avatar :name="$u->name" size="xl" :src="$u->avatarUrl()" />
        <div class="aum-id__txt">
            <h2 id="aumTitle">{{ $u->name }}</h2>
            <p class="aum-id__sub">{{ $u->email }}@if (count($roleLabels)) · {{ implode(', ', $roleLabels) }}@endif</p>
            <span class="aum-state {{ $stateClass }}">{{ $stateLabel }}</span>
        </div>
    </header>

    @if ($isTrashed)
        <div class="aum-banner aum-banner--warn">
            <span>Tài khoản này đã bị xóa. Khôi phục để đăng nhập và chỉnh sửa lại; lịch sử vẫn được giữ nguyên.</span>
            <button type="button" class="aum-btn aum-btn--primary" data-restore="{{ $u->id }}">Khôi phục người dùng</button>
        </div>
    @endif

    <div class="aum-tabs" role="tablist" aria-label="Chi tiết người dùng">
        <button type="button" role="tab" id="aumTabInfo" data-tab="info" aria-controls="aumPaneInfo" aria-selected="true">Thông tin</button>
        <button type="button" role="tab" id="aumTabPerms" data-tab="perms" aria-controls="aumPanePerms" aria-selected="false" tabindex="-1">Phân quyền</button>
        <button type="button" role="tab" id="aumTabHistory" data-tab="history" aria-controls="aumPaneHistory" aria-selected="false" tabindex="-1">Lịch sử <span class="aum-count">{{ count($history) }}</span></button>
    </div>

    {{-- ===== TAB 1: THÔNG TIN (cập nhật toàn bộ thông tin + mật khẩu) ===== --}}
    <section class="aum-pane" id="aumPaneInfo" data-pane="info" role="tabpanel" aria-labelledby="aumTabInfo">
        <div class="aum-grid">
            <div class="aum-col">
                <form class="aum-card" id="aumInfoForm" method="POST" action="{{ route('admin.users.update', $u->id) }}" novalidate>
                    <h3>Thông tin liên hệ</h3>
                    <div class="aum-field">
                        <label for="aumName">Họ tên <b aria-hidden="true">*</b></label>
                        <input id="aumName" name="name" type="text" maxlength="255" required value="{{ $u->name }}" autocomplete="off">
                        <span class="aum-err" data-err="name"></span>
                    </div>
                    <div class="aum-row2">
                        <div class="aum-field">
                            <label for="aumEmail">Email <b aria-hidden="true">*</b></label>
                            <input id="aumEmail" name="email" type="email" maxlength="255" required value="{{ $u->email }}" autocomplete="off">
                            <span class="aum-err" data-err="email"></span>
                        </div>
                        <div class="aum-field">
                            <label for="aumPhone">Số điện thoại</label>
                            <input id="aumPhone" name="phone" type="text" maxlength="30" value="{{ $u->phone }}" autocomplete="off">
                            <span class="aum-err" data-err="phone"></span>
                        </div>
                    </div>
                    <div class="aum-row2">
                        <div class="aum-field">
                            <label for="aumProvince">Tỉnh/Thành</label>
                            <input id="aumProvince" name="province" type="text" maxlength="100" value="{{ $u->province }}" autocomplete="off">
                            <span class="aum-err" data-err="province"></span>
                        </div>
                        <div class="aum-field">
                            <label for="aumRegion">Khu vực</label>
                            <select id="aumRegion" name="region">
                                <option value="">— Chưa chọn —</option>
                                @foreach ($regions as $k => $label)
                                    <option value="{{ $k }}" @selected($u->region === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="aum-err" data-err="region"></span>
                        </div>
                    </div>
                    <p class="aum-msg" data-msg="info" role="status"></p>
                    <div class="aum-actions">
                        <button type="button" class="aum-btn" data-reset="info" disabled>Hủy đổi thông tin</button>
                        <button type="submit" class="aum-btn aum-btn--primary" data-save="info" disabled>Lưu thông tin</button>
                    </div>
                </form>

                <form class="aum-card" id="aumPwForm" method="POST" action="{{ route('admin.users.password.update', $u->id) }}" novalidate>
                    <h3>Đặt lại mật khẩu</h3>
                    <p class="aum-hint">Đặt mật khẩu mới trực tiếp cho người dùng này (dùng khi họ không tự đổi được). Mật khẩu không được ghi vào lịch sử.</p>
                    <div class="aum-row2">
                        <div class="aum-field">
                            <label for="aumPw">Mật khẩu mới</label>
                            <input id="aumPw" name="password" type="password" minlength="8" autocomplete="new-password">
                            <span class="aum-err" data-err="password"></span>
                        </div>
                        <div class="aum-field">
                            <label for="aumPw2">Xác nhận mật khẩu</label>
                            <input id="aumPw2" name="password_confirmation" type="password" minlength="8" autocomplete="new-password">
                            <span class="aum-err" data-err="password_confirmation"></span>
                        </div>
                    </div>
                    <p class="aum-msg" data-msg="pw" role="status"></p>
                    <div class="aum-actions">
                        <button type="submit" class="aum-btn aum-btn--primary" data-save="pw">Đổi mật khẩu</button>
                    </div>
                </form>
            </div>

            <div class="aum-col">
                <div class="aum-card aum-card--mint">
                    <h3>Tài khoản</h3>
                    <dl class="aum-dl">
                        <div><dt>Vai trò</dt><dd>{{ count($roleLabels) ? implode(', ', $roleLabels) : '— Chưa có —' }}</dd></div>
                        @if ($profileLine)
                            <div><dt>Hồ sơ</dt><dd>{{ $profileLine }}</dd></div>
                        @endif
                        <div><dt>Ngày tạo</dt><dd>{{ $u->created_at?->format('d/m/Y H:i') }}</dd></div>
                    </dl>
                    <hr>
                    <h4>Trạng thái tài khoản</h4>
                    <p class="aum-hint">Khóa để tạm ngừng đăng nhập, giữ nguyên thông tin và các quyền đã cấp. Bấm "Lưu thông tin" để áp dụng.</p>
                    <div class="aum-field">
                        <label for="aumStatus" class="aum-sr">Trạng thái</label>
                        <select id="aumStatus" name="status" form="aumInfoForm" data-orig="{{ $u->status === 'suspended' ? 'suspended' : 'active' }}">
                            <option value="active" @selected($u->status !== 'suspended')>{{ $stateLabelFor('active') }}</option>
                            <option value="suspended" @selected($u->status === 'suspended')>{{ $stateLabelFor('suspended') }}</option>
                        </select>
                        <span class="aum-err" data-err="status"></span>
                    </div>
                    <div class="aum-field" data-reason-box hidden>
                        <label for="aumReason">Lý do khóa <b aria-hidden="true">*</b></label>
                        <textarea id="aumReason" name="reason" form="aumInfoForm" rows="2" maxlength="1000" placeholder="Ví dụ: Tài khoản vi phạm quy định…"></textarea>
                        <span class="aum-err" data-err="reason"></span>
                    </div>
                </div>

                <form class="aum-card" id="aumRolesForm" method="POST" action="{{ route('admin.users.roles.update', $u->id) }}">
                    <h3>Vai trò</h3>
                    @if ($isSelf)
                        <p class="aum-hint">Không thể tự sửa vai trò của chính mình ở đây — nhờ một quản trị viên khác thao tác nếu cần đổi.</p>
                    @else
                        <div class="aum-roles">
                            @foreach ($availableRoles as $key => $label)
                                <label><input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $roleNames, true))> {{ $label }}</label>
                            @endforeach
                        </div>
                        <span class="aum-err" data-err="roles"></span>
                        <p class="aum-msg" data-msg="roles" role="status"></p>
                        <div class="aum-actions">
                            <button type="submit" class="aum-btn" data-save="roles">Lưu vai trò</button>
                        </div>
                    @endif
                </form>

                @unless ($isTrashed)
                    <div class="aum-card aum-card--danger">
                        <h3>Xóa người dùng</h3>
                        <p class="aum-hint">Xóa mềm: tài khoản không đăng nhập được nữa nhưng dữ liệu được giữ và có thể khôi phục ở tab "Đã xóa".</p>
                        <div class="aum-actions">
                            <button type="button" class="aum-btn aum-btn--danger" data-del="{{ $u->id }}" data-name="{{ $u->name }}" @disabled($isSelf) @if ($isSelf) title="Không thể tự xóa chính mình" @endif>
                                <x-lucide name="trash-2" class="h-4 w-4" /> Xóa người dùng
                            </button>
                        </div>
                    </div>
                @endunless
            </div>
        </div>
    </section>

    {{-- ===== TAB 2: PHÂN QUYỀN ===== --}}
    <section class="aum-pane" id="aumPanePerms" data-pane="perms" role="tabpanel" aria-labelledby="aumTabPerms" hidden>
        @if ($matrix['note'])
            <div class="aum-note @if ($readonlyPerm) aum-note--lock @endif">{{ $matrix['note'] }}</div>
        @endif

        @if ($matrixOn)
            <form id="aumPermForm" method="POST" action="{{ route('admin.users.permissions.update', $u->id) }}" data-readonly="{{ $readonlyPerm ? 1 : 0 }}">
                <div class="aum-permhead">
                    <div>
                        <h3>Chọn chức năng và phạm vi được phép</h3>
                        <p class="aum-hint">Phạm vi cho biết chức năng áp dụng trên dữ liệu nào.</p>
                    </div>
                    <span class="aum-permcount"><b data-perm-count>{{ $matrix['granted'] }}</b>/{{ $matrix['total'] }} đã cấp</span>
                </div>

                <div class="aum-permtable" role="group" aria-label="Danh sách chức năng">
                    <div class="aum-permcols" aria-hidden="true"><span>Chức năng</span><span>Phạm vi</span></div>
                    @foreach ($matrix['groups'] as $g)
                        <fieldset class="aum-permgroup">
                            <legend>{{ $g['label'] }}</legend>
                            @foreach ($g['items'] as $it)
                                <div class="aum-perm" data-perm data-slug="{{ $it['slug'] }}" data-on="{{ $it['on'] ? 1 : 0 }}" data-scope="{{ $it['scope'] }}">
                                    <label class="aum-perm__main">
                                        <input type="checkbox" @checked($it['on']) @disabled($readonlyPerm)>
                                        <span>{{ $it['label'] }}</span>
                                        @if (isset($tagText[$it['state']]))
                                            <em class="aum-tag aum-tag--{{ $it['state'] }}">{{ $tagText[$it['state']] }}</em>
                                        @endif
                                    </label>
                                    <select aria-label="Phạm vi: {{ $it['label'] }}" @disabled($readonlyPerm || ! $it['on'])>
                                        @foreach ($scopes as $sk => $sl)
                                            <option value="{{ $sk }}" @selected($it['scope'] === $sk)>{{ $sl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>

                {{-- SỬA 28/10 (khách: "phân quyền cho các tính năng bên sidebar admin: ai được vào, thêm, sửa, xoá") — QUYỀN KHU
                     QUẢN TRỊ: mỗi dòng 1 mục menu admin, mỗi cột 1 thao tác. Ô "—" = mục đó không có thao tác này. Tick Thêm/Sửa/
                     Xoá/Duyệt thì tự tick Xem; bỏ Xem thì bỏ hết dòng. Lưu chung nút "Lưu quyền". Xem App\Support\AdminPermissions. --}}
                @if (! empty($matrix['adminModules']))
                    <div class="aum-adm" role="group" aria-label="Quyền khu quản trị">
                        <div class="aum-adm__head">
                            <div>
                                <h3>Quyền khu quản trị (menu Admin)</h3>
                                <p class="aum-hint">Mục nào không có quyền <b>Xem</b> thì không hiện trên menu và không mở được.</p>
                            </div>
                            @unless ($readonlyPerm)
                                <div class="aum-adm__bulk">
                                    <button type="button" class="aum-btn" data-admin-all="1">Cấp tất cả</button>
                                    <button type="button" class="aum-btn" data-admin-all="0">Bỏ hết</button>
                                </div>
                            @endunless
                        </div>
                        <div class="aum-adm__table">
                            <div class="aum-adm__row aum-adm__row--head" aria-hidden="true">
                                <span>Mục quản trị</span>
                                @foreach (\App\Support\AdminPermissions::ACTIONS as $aLabel)
                                    <span>{{ $aLabel }}</span>
                                @endforeach
                            </div>
                            @foreach ($matrix['adminModules'] as $am)
                                <div class="aum-adm__row" data-admin-row>
                                    <div class="aum-adm__name">
                                        <b>{{ $am['label'] }}</b>
                                        @if ($am['hint'] !== '')<small>{{ $am['hint'] }}</small>@endif
                                    </div>
                                    @foreach ($am['cells'] as $aKey => $cell)
                                        @if ($cell === null)
                                            <span class="aum-adm__na" aria-hidden="true">—</span>
                                        @else
                                            <div class="aum-adm__cell" data-perm data-admin data-action="{{ $aKey }}" data-slug="{{ $cell['slug'] }}" data-on="{{ $cell['on'] ? 1 : 0 }}" data-scope="own">
                                                <label title="{{ $cell['label'] }}">
                                                    <input type="checkbox" @checked($cell['on']) @disabled($readonlyPerm) aria-label="{{ $cell['label'] }}">
                                                    <span class="aum-adm__lbl">{{ \App\Support\AdminPermissions::ACTIONS[$aKey] }}</span>
                                                </label>
                                                <select hidden aria-hidden="true" tabindex="-1"><option value="own" selected>own</option></select>
                                                @if (isset($tagText[$cell['state']]))
                                                    <em class="aum-tag aum-tag--{{ $cell['state'] }}">{{ $tagText[$cell['state']] }}</em>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="aum-msg" data-msg="perms" role="status"></p>
                <span class="aum-err" data-err="permissions"></span>
                @unless ($readonlyPerm)
                    <div class="aum-permfoot">
                        <span class="aum-permstate" data-perm-state>Không có thay đổi mới</span>
                        <div class="aum-actions">
                            <button type="button" class="aum-btn" data-reset="perms" disabled>Hủy đổi quyền</button>
                            <button type="submit" class="aum-btn aum-btn--primary" data-save="perms" disabled>Lưu quyền</button>
                        </div>
                    </div>
                @endunless
            </form>
        @endif
    </section>

    {{-- ===== TAB 3: LỊCH SỬ (audit log của người dùng, chia trang 10 mục) ===== --}}
    <section class="aum-pane" id="aumPaneHistory" data-pane="history" role="tabpanel" aria-labelledby="aumTabHistory" hidden>
        @if (count($history) === 0)
            <div class="aum-empty">Chưa có thay đổi nào được ghi nhận cho người dùng này.</div>
        @else
            <ol class="aum-log" data-hist>
                @foreach ($history as $h)
                    <li class="aum-log__item aum-log__item--{{ $h['tone'] }}" data-hist-item>
                        <strong>{{ $h['title'] }}</strong>
                        <span class="aum-log__meta">{{ $h['when'] }} · {{ $h['actor'] }}</span>
                        @foreach ($h['lines'] as $line)
                            <span class="aum-log__line">{{ $line }}</span>
                        @endforeach
                        @if ($h['reason'])
                            <span class="aum-log__line aum-log__reason">Lý do: {{ $h['reason'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
            <div class="aum-pg" data-hist-nav hidden></div>
        @endif
    </section>
</div>

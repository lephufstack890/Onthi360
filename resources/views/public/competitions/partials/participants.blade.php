{{-- SỬA 24/10 — tab "Học sinh" của ban tổ chức (source: ContestParticipants). Duyệt / từ chối / thêm học sinh
     gửi về competitions.manage.* (ContestManageController) — quyền kiểm ở máy chủ. --}}
@php $p = $participants; @endphp
<div class="hub-content-heading contest-participants-heading">
    <div><h3>Học sinh tham gia</h3><p>Danh sách toàn sự kiện · Kết quả: {{ $round['label'] }}</p></div>
    <div>
        <button type="button" class="contest-btn secondary" @click="showTab('{{ $survey ? 'ranking' : 'results' }}')"><x-lucide name="printer" />Báo cáo &amp; in</button>
        <button type="button" class="contest-btn primary" @disabled($closed) @click="openAdd()"><x-lucide name="user-plus" />Thêm học sinh</button>
    </div>
</div>
<div class="contest-participant-counts"><span><b>{{ count($p['rows']) }}</b> học sinh</span><span><b>{{ $p['pending'] }}</b> chờ duyệt</span><span><b>{{ $p['submitted'] }}</b> đã nộp trong kỳ này</span></div>
@if ($closed)
    <p class="hub-assessment-note">Sự kiện đã kết thúc. Bạn có thể xem danh sách và in báo cáo.</p>
@endif
@if ($survey)
    <p class="hub-assessment-note">Chỉ học sinh có trong danh sách và nhập đúng mật khẩu mới làm được khảo sát. Thêm học sinh bằng nút "Thêm học sinh"; bấm "Gỡ" để đưa học sinh ra khỏi danh sách (bài đã nộp vẫn giữ).</p>
@endif
<label class="contest-participant-search"><x-lucide name="search" /><input type="search" aria-label="Tìm trong danh sách tham gia" placeholder="Tìm tên, lớp hoặc tài khoản…" x-model="peopleQuery"></label>
@if (count($p['rows']))
    <div class="contest-table-scroll">
        <table>
            <thead><tr><th>Học sinh</th><th>Tham gia</th><th>Kết quả kỳ này</th><th>Người thêm</th><th></th></tr></thead>
            <tbody>
                @foreach ($p['rows'] as $row)
                    <tr x-show="peopleMatch({{ Js::from($row['search']) }})">
                        <td><b>{{ $row['name'] }}</b><small class="hub-ranking-class">{{ $row['className'] ?: $row['account'] }}</small></td>
                        <td><span class="contest-badge {{ $row['tone'] }}">{{ $row['statusLabel'] }}</span>
                            @if ($row['status'] === 'rejected' && $row['rejectReason'])<small class="hub-ranking-class">{{ $row['rejectReason'] }}</small>@endif
                        </td>
                        <td>@if ($row['resultNote'])<b>{{ $row['resultLabel'] }}</b><small class="hub-ranking-class">{{ $row['resultNote'] }}</small>@else{{ $row['resultLabel'] }}@endif</td>
                        <td>{{ $row['addedLabel'] }}</td>
                        <td>
                            <div class="contest-approval-actions">
                            @if ($row['status'] === 'pending' && ! $closed && $row['registrationId'])
                                    <form method="POST" action="{{ route('competitions.manage.reject', ['competition' => $contest['id'], 'registration' => $row['registrationId']]) }}" onsubmit="return confirm('Từ chối đăng ký này?');">
                                        @csrf<input type="hidden" name="vong" value="{{ $round['id'] }}">
                                        <button type="submit" class="contest-btn secondary">Từ chối</button>
                                    </form>
                                    <form method="POST" action="{{ route('competitions.manage.approve', ['competition' => $contest['id'], 'registration' => $row['registrationId']]) }}">
                                        @csrf<input type="hidden" name="vong" value="{{ $round['id'] }}">
                                        <button type="submit" class="contest-btn primary">Duyệt</button>
                                    </form>
                            @endif
                            {{-- SỬA 24/10 — gỡ học sinh khỏi danh sách (ContestManageController::remove). --}}
                            @if ($row['registrationId'])
                                <form method="POST" action="{{ route('competitions.manage.remove', ['competition' => $contest['id'], 'registration' => $row['registrationId']]) }}"
                                      onsubmit="return confirm({{ Js::from('Gỡ '.$row['name'].' khỏi danh sách? Học sinh sẽ không vào làm bài được nữa; bài đã nộp vẫn giữ.') }});">
                                    @csrf<input type="hidden" name="vong" value="{{ $round['id'] }}">
                                    <button type="submit" class="contest-btn secondary contest-kick" title="Gỡ khỏi danh sách"><x-lucide name="x" />Gỡ</button>
                                </form>
                            @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="contest-empty"><x-lucide name="users" /><h3>Chưa có học sinh trong danh sách</h3><p>Thêm học sinh từ lớp có sẵn hoặc chờ học sinh đăng ký, tham gia.</p></div>
@endif

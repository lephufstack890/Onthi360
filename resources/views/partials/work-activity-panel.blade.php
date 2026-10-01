{{--
    SỬA 1/10 (khách: "các tab chỗ bắt đầu làm đề khi click vào nó phải như này nè cho đồng bộ")
    — KHUNG tab "Nhật ký", tách ra partial để màn Luyện tập và màn Phòng thi dùng CHUNG một bản.
    Trước đây chỉ màn Luyện tập có tab này nên hai màn lệch nhau 1 tab.

    Biến truyền vào:
      · $activityTabExpr  biểu thức Alpine quyết định tab đang mở. Màn Luyện tập dùng biến `tab`,
                          màn Phòng thi dùng `activeTab` — nên phải truyền vào chứ không ghi cứng.

    Phần JS đi kèm nằm ở partials/work-activity-log — PHẢI include cả hai, có khung mà thiếu
    script thì tab hiện ra nhưng trống trơn.
--}}
                {{-- ───────── TAB: NHẬT KÝ ─────────
                     SỬA 30/9 — tab thứ 5 của bản mẫu mới (ActivityPanel). Ghi lại các mốc trong
                     lúc làm bài ngay TẠI TRÌNH DUYỆT (sessionStorage), KHÔNG gửi gì về máy chủ:
                     mở bài, chuyển tab, dán/sao chép, cửa sổ mất tiêu điểm, phím Print Screen,
                     rời trang, nộp bài. Danh sách do JS ở cuối trang vẽ (không dùng Alpine bên
                     trong để khỏi vướng #practice-container bị thay mới sau mỗi lần chấm). --}}
                {{-- {!! !!} chứ không {{ }}: giá trị là BIỂU THỨC Alpine do chính 2 view trong mã truyền
     vào (không bao giờ là dữ liệu người dùng), escape thì dấu nháy thành &#039; — trình duyệt
     vẫn giải mã đúng khi Alpine đọc, nhưng xem mã nguồn trang thì rối mắt không cần thiết. --}}
                <section x-show="{!! $activityTabExpr ?? "tab === 'activity'" !!}" x-cloak class="h-full min-h-0 overflow-y-auto p-3 sm:p-4">
                    <div class="mx-auto max-w-3xl rounded-xl border border-[#DDEAF0] bg-white px-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[#DDEAF0] py-2.5">
                            <h3 class="text-xs font-bold text-[#123B68]">Nhật ký làm bài</h3>
                            <span class="text-[10px] text-[#607A90]" data-activity-count>0 sự kiện · 0 dấu hiệu cần xem xét</span>
                            <button type="button" data-activity-download class="ml-auto min-h-8 text-[10px] font-semibold text-[#126F91] underline underline-offset-2">Tải nhật ký</button>
                        </div>
                        <div class="flex gap-3 border-b border-[#DDEAF0] py-2 text-[10px]" aria-label="Lọc nhật ký">
                            <button type="button" data-activity-filter="all" class="font-bold text-[#126F91] underline underline-offset-2">Tất cả</button>
                            <button type="button" data-activity-filter="signals" class="text-[#607A90]">Dấu hiệu (<span data-activity-signal-count>0</span>)</button>
                        </div>
                        <ol class="divide-y divide-[#EEF3F6]" data-activity-list></ol>
                        <p class="border-t border-[#DDEAF0] py-2 text-[10px] text-[#7A92A3]">
                            Đây là tín hiệu để đối chiếu, không tự kết luận vi phạm. Nhật ký chỉ nằm trong phiên trình duyệt này.
                        </p>
                    </div>
                </section>

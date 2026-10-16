{{-- ═══════ VIÊN "ĐÃ LÀM · ĐIỂM GẦN NHẤT" Ở THANH DƯỚI (dùng chung) ═══════

     SỬA 30/9 (10) — tách ra partial vì hai màn cùng dùng và phải giống hệt nhau:
       · student/practice/exercise-play.blade.php  (làm 1 bài tập chuyên đề)
       · student/assessment/take.blade.php         (phòng thi)

     Viết CSS thường chứ không dùng class Tailwind mới: máy chủ KHÔNG chạy được vite nên
     class nào chưa có sẵn trong public/build/assets/app-*.css sẽ không có tác dụng. --}}
<style>
    .oi-done-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 34px;
        padding: 4px 11px;
        border: 1px solid #CBE8D8;
        border-radius: 12px;
        background: #EAF7F0;
        color: #2F8A6B;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.35;
        white-space: nowrap;
    }
    .oi-done-chip__dot {
        flex: 0 0 auto;
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #34A853;
    }
    /* .hidden của Tailwind cùng độ ưu tiên với .oi-done-chip mà lại đứng TRƯỚC trong tệp CSS,
       nên không có dòng này thì viên "chưa làm" vẫn hiện ra (rỗng). */
    .oi-done-chip.hidden { display: none !important; }
    /* Màn hẹp: giấu bớt cho thanh dưới khỏi chật, nút nộp vẫn là thứ quan trọng nhất. */
    @media (max-width: 767px) { .oi-done-chip { display: none; } }
    html.theme-dark .assessment-modal .oi-done-chip {
        border-color: #2f5f4c;
        background: #1d3b30;
        color: #8ed6b4;
    }
    /* SỬA 16/10 (khách: "in đúc như source") — viên ở thanh dưới theo đúng AssessmentModal.jsx: bo 8px, không chấm tròn,
       "Chưa làm" màu vàng (#E9D7B5 / #FFF6E5 / #91601B), "Đã làm" màu xanh (#A9DCC5 / #E5F7EE / #207153); bấm được. */
    .oi-done-chip { min-height: 36px; padding: 4px 9px; border-radius: 8px; border-color: #A9DCC5; background: #E5F7EE; color: #207153; cursor: pointer; }
    .oi-done-chip__dot { display: none; }
    .oi-foot-title, .oi-foot-hint { margin: 0; font-size: 12px; line-height: 1.45; }
    .oi-done-chip.is-todo { border-color: #E9D7B5; background: #FFF6E5; color: #91601B; }
    html.theme-dark .assessment-modal .oi-done-chip.is-todo { border-color: #8A6A33; background: transparent; color: #E2B266; }
</style>

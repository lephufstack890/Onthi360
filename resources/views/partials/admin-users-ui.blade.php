{{-- KIỂU CHUNG CHO MÀN "NGƯỜI DÙNG" CỦA ADMIN (danh sách, chi tiết, form tạo/sửa).

     SỬA 8/10 (khách: "cập nhật lại UI màn người dùng theo source mới, logic giữ nguyên") — chuyển phong cách
     của education-main/src/components/AdminUsers.jsx + adminUsers.css (khung "Người dùng" có dải tab vai trò
     kèm số đếm, thanh tìm + ô lọc trạng thái, bảng ô định danh avatar + tên + email, nhãn vai trò, chấm trạng
     thái) sang Blade. Dùng lại khối .acx-/.apx-/.akx- của các màn đã làm, chỉ thêm phần riêng tiền tố .aux-.
     Không route, không tên field, không truy vấn nào đổi. --}}
@include('partials.admin-content-ui')
<style>
    .aux-who{display:flex;align-items:center;gap:12px;min-width:0}
    .aux-who>div{min-width:0}
    .aux-email{display:block;margin-top:2px;font-size:12px;color:#637b92;overflow-wrap:anywhere}
    .aux-roles{display:flex;flex-wrap:wrap;gap:6px}
    .aux-role{display:inline-flex;align-items:center;padding:3px 9px;border-radius:7px;background:#eef3f9;color:#4b627a;font-size:12px;font-weight:600;white-space:nowrap}
    .aux-role--admin{background:#f3ecfb;color:#6b3fa0}
    .aux-role--teacher{background:#e6effc;color:#2554a3}
    .aux-role--parent{background:#fdf1d8;color:#8a5f10}
    .aux-role--student{background:#e8f3ee;color:#236654}
    .aux-status{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#236654;white-space:nowrap}
    .aux-status::before{content:"";width:7px;height:7px;border-radius:50%;background:#10b981}
    .aux-status--warn{color:#9a6a12}
    .aux-status--warn::before{background:#f59e0b}
    .aux-status--off{color:#945463}
    .aux-status--off::before{background:#e11d48}
    .aux-date{font-size:13px;color:#637b92;white-space:nowrap}
    .aux-rolebox{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
    .aux-rolebox label{display:inline-flex;align-items:center;gap:8px;min-height:38px;padding:0 14px;border:1px solid #d7e3f0;border-radius:12px;background:#fff;color:#345674;font-size:13px;font-weight:600;cursor:pointer;transition:border-color .15s,background .15s}
    .aux-rolebox label:hover{border-color:#93c5fd;background:#f0f7ff}
    .aux-rolebox input{accent-color:#2563eb}
    .aux-sub{padding:12px 14px;border:1px solid #d8e5f2;border-radius:12px;background:#edf4fc;font-size:13px;color:#345674}
    .aux-sub__row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .aux-sub+.aux-sub{margin-top:8px}
    .aux-h{margin:0 0 8px;font-size:12px;font-weight:700;color:#587089;text-transform:uppercase;letter-spacing:.04em}
    .aux-log{padding:10px 0;border-bottom:1px solid #d4e5e1;font-size:13px}
    .aux-log:last-child{border-bottom:0}
    .aux-log p{margin:0}
    .aux-log em{display:block;margin-top:2px;font-size:12px;color:#5d786f}
    .aux-log small{display:block;margin-top:3px;font-size:12px;color:#5d786f}
    .aux-pwbox{max-width:420px}
    .aux-pw{position:relative}
    .aux-pw .admin-input{padding-right:42px}
    .aux-pw button{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:none;cursor:pointer;color:#8aa0b6}
    .aux-act{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:10px}
    .aux-act form{margin:0}
    .aux-reject{display:flex;gap:8px;margin-top:8px}
</style>

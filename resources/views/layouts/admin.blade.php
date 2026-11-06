{{-- Khu Quản trị.
     SỬA 12/9 — trước đây file này @extends('layouts.app') dùng chung cho 3 vai trò, nên sửa
     giao diện một khu là đổi luôn các khu còn lại. Giờ cả 4 khu cùng dùng layouts/workspace
     (đúng cách source gom vào một <RoleWorkspace> với roleConfig), phân biệt bằng $wsRole. --}}
{{-- SỬA 6/11 — admin đang "xem như Giáo viên" mở trang Kho nội dung (admin.content.*): giữ khung khu Giáo viên. --}}
@extends('layouts.workspace', ['wsRole' => auth()->user()?->actingSessionRole() === \App\Models\Role::TEACHER ? 'teacher' : 'admin'])

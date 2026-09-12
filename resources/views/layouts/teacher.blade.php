{{-- Khu Giáo viên.
     SỬA 12/9 — trước đây file này @extends('layouts.app') dùng chung cho 3 vai trò, nên sửa
     giao diện một khu là đổi luôn các khu còn lại. Giờ cả 4 khu cùng dùng layouts/workspace
     (đúng cách source gom vào một <RoleWorkspace> với roleConfig), phân biệt bằng $wsRole. --}}
@extends('layouts.workspace', ['wsRole' => 'teacher'])

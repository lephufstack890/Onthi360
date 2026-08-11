<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng role tối giản, tự viết (không dùng package ngoài) để giữ base không
     * phụ thuộc gói bên thứ ba nào ngoài Laravel core — dễ kiểm chứng và ít rủi
     * ro tương thích phiên bản về lâu dài. Một user có thể có NHIỀU role đồng
     * thời (ví dụ vừa là giáo viên vừa là phụ huynh) — đúng yêu cầu "role
     * switcher" ở mục 4.3.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique(); // student|parent|teacher|editor|admin|super_admin
            $table->string('label', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

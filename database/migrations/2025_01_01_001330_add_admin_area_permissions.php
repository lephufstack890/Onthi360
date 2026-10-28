<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 28/10 (khách: "phân quyền cho các tính năng bên sidebar admin — ai được vào, thêm, sửa, xoá") — tạo danh mục quyền
 * khu quản trị "admin.<mục>.<thao tác>" theo App\Support\AdminPermissions. Admin mặc định có hết, Editor mặc định không có
 * gì (cấp riêng ở Người dùng > Phân quyền), Super Admin luôn có hết. Chạy lại nhiều lần không sao (bỏ qua slug đã có).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach (\App\Support\AdminPermissions::MODULES as $module => $m) {
            foreach ($m['actions'] as $action) {
                $slug = \App\Support\AdminPermissions::slug($module, $action);
                if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                    DB::table('permissions')->insert([
                        'slug' => $slug,
                        'label' => \App\Support\AdminPermissions::label($module, $action),
                        'group' => 'admin',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('slug', 'like', 'admin.%')->delete();
        }
    }
};

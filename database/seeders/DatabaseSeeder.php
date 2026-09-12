<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        // Câu chuyện đồng hành MẪU cho trang chủ — tạo ở dạng bản nháp, xem TestimonialSeeder.
        $this->call(TestimonialSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}

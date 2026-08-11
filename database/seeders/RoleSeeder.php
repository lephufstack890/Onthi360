<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [Role::STUDENT, 'Học sinh'],
            [Role::PARENT, 'Phụ huynh'],
            [Role::TEACHER, 'Giáo viên'],
            [Role::EDITOR, 'Content Editor'],
            [Role::ADMIN, 'Admin'],
            [Role::SUPER_ADMIN, 'Super Admin'],
        ];

        foreach ($roles as [$name, $label]) {
            Role::query()->firstOrCreate(['name' => $name], ['label' => $label]);
        }
    }
}

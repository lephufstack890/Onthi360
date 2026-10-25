<?php

namespace Database\Seeders;

use App\Services\Contest\CourseCompetitionProvisioner;
use Illuminate\Database\Seeder;

/**
 * SỬA 25/10 (khách: "server đã có dữ liệu lộ trình thật — seed để các khoá học cũ có đủ cuộc thi + khảo sát") —
 * sinh bù 1 cuộc thi + 1 khảo sát cho MỌI khoá học đang có. Chạy lại bao nhiêu lần cũng được (khoá đã đủ thì bỏ qua).
 *
 *   php artisan db:seed --class=CourseCompetitionSeeder
 */
class CourseCompetitionSeeder extends Seeder
{
    public function run(CourseCompetitionProvisioner $provisioner): void
    {
        if (! $provisioner->ready()) {
            $this->command?->warn('Chưa chạy migration (cột competitions.course_id) — chạy "php artisan migrate" trước.');

            return;
        }

        $r = $provisioner->ensureAll();
        $this->command?->info('Đã kiểm tra '.$r['courses'].' khoá học, tạo mới '.$r['created'].' cuộc thi / khảo sát.');
    }
}

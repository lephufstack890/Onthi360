<?php

namespace App\Observers;

use App\Models\Course;
use App\Services\Contest\CourseCompetitionProvisioner;

/**
 * SỬA 25/10 — khoá học (lộ trình) MỚI tạo ở bất kỳ đâu (trang admin, nhập liệu, seeder…) tự có 1 cuộc thi + 1 khảo sát.
 * Đăng ký ở App\Providers\AppServiceProvider::boot(). Luật tạo: App\Services\Contest\CourseCompetitionProvisioner.
 */
class CourseCompetitionObserver
{
    public function __construct(private readonly CourseCompetitionProvisioner $provisioner) {}

    public function created(Course $course): void
    {
        $this->provisioner->ensureQuietly($course);
    }

    /** Khoá học được khôi phục từ thùng rác mà chưa có cuộc thi / khảo sát → sinh bù. */
    public function restored(Course $course): void
    {
        $this->provisioner->ensureQuietly($course);
    }
}

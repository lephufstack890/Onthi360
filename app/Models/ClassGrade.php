<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 6/11 — điểm giáo viên chấm / sửa (thang 10) + nhận xét cho một bài giao của một học sinh trong lớp (tab "Kết quả học
 * tập"). assignment_id = NULL: nhận xét chung của học sinh đó trong lớp. score = NULL: giữ điểm tự động của bài làm.
 */
class ClassGrade extends Model
{
    protected $fillable = ['class_room_id', 'student_id', 'assignment_id', 'score', 'comment', 'graded_by'];

    protected $casts = ['score' => 'float'];

    public static function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('class_grades');
    }
}

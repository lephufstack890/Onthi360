<?php

namespace App\Support;

use App\Enums\CompetitionType;

/**
 * SỬA 25/10 (khách: "loại cuộc thi thì title là Cuộc thi đánh giá năng lực + tên lộ trình; loại khảo sát thì Khảo sát
 * năng lực + tên lộ trình") — MỘT chỗ duy nhất quyết định tên hiển thị của cuộc thi / khảo sát gắn với khoá học
 * ("lộ trình" trên giao diện = khoá học, competitions.course_id). Muốn đổi chữ mặc định: sửa 2 hằng số dưới.
 *
 * Dùng ở: Competition::displayTitle() (mọi trang hiển thị), CourseCompetitionProvisioner (tên lưu khi tự sinh).
 */
final class CompetitionNaming
{
    public const CONTEST_PREFIX = 'Cuộc thi đánh giá năng lực';

    public const SURVEY_PREFIX = 'Khảo sát năng lực';

    public static function prefix(CompetitionType|string|null $type): string
    {
        $value = $type instanceof CompetitionType ? $type->value : (string) $type;

        return $value === CompetitionType::Survey->value ? self::SURVEY_PREFIX : self::CONTEST_PREFIX;
    }

    /** "Cuộc thi đánh giá năng lực · Python thi đấu THCS". Chưa gắn khoá học → null (dùng tên đã lưu). */
    public static function forCourse(CompetitionType|string|null $type, ?string $courseTitle): ?string
    {
        $courseTitle = trim((string) $courseTitle);

        return $courseTitle === '' ? null : self::prefix($type).' · '.$courseTitle;
    }
}

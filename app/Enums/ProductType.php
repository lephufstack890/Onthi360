<?php

namespace App\Enums;

enum ProductType: string
{
    case Book = 'book';
    case Topic = 'topic';
    case Exam = 'exam';
    case Course = 'course';

    /**
     * SỬA 4/9 (khách yêu cầu "Chương/Phần/Đề" — "nếu loại sách thì thêm chương, loại chuyên
     * đề là thêm phần, loại bộ đề thì thêm đề"): nhãn hiển thị cho mục lục (materials.type=
     * chapter) của TỪNG loại sản phẩm — Khóa học (Course) trả về null vì khách không nêu loại
     * này trong yêu cầu, nên KHÔNG hiện khối "Chương/Phần/Đề" cho sản phẩm loại Khóa học (xem
     * ContentService::productChaptersFor(), admin/products/show.blade.php).
     */
    public function chapterLabel(): ?string
    {
        return match ($this) {
            self::Book => 'Chương',
            self::Topic => 'Phần',
            self::Exam => 'Đề',
            self::Course => null,
        };
    }
}

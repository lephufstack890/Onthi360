<?php

namespace App\Support;

/**
 * SỬA 9/10 (khách: "chọn loại tài liệu xong thì cho chọn ảnh bìa, ảnh bìa lấy từ catalog") — catalog
 * ảnh bìa tài liệu, bê từ education-main (src/data/materials.js + utils/adminContent.js
 * DOCUMENT_COVERS: mỗi loại Sách/Chuyên đề/Bộ đề có sẵn vài ảnh bìa để chọn).
 *
 * Ảnh bìa chọn từ catalog được lưu vào products.cover_image_path dạng "catalog:<id>" (ví dụ
 * "catalog:book-1") — KHÔNG sinh file mới và không cần migration. Ảnh admin tải lên từ trước vẫn là
 * đường dẫn tương đối trên đĩa public như cũ. Mọi chỗ hiển thị ảnh bìa tài liệu gọi url() để hiểu cả hai.
 */
final class ProductCover
{
    public const PREFIX = 'catalog:';

    /** Loại tài liệu (products.type) → nhóm trong catalog. */
    private const GROUP_BY_TYPE = ['book' => 'books', 'topic' => 'topics', 'exam' => 'exams'];

    /**
     * Danh sách ảnh trong catalog — thứ tự + tiêu đề + phân nhóm giống source mới
     * (book-2 thuộc "Chuyên đề", book-3/book-4 thuộc "Bộ đề").
     *
     * @return array<string, array{id:string,title:string,file:string,group:string}>
     */
    public static function catalog(): array
    {
        $items = [
            ['book-1', 'Lập trình căn bản và Thuật toán với Python 3', 'book-img-1.jpg', 'books'],
            ['book-2', 'Chuyên đề Cấu trúc dữ liệu và Giải thuật chuyên sâu', 'book-img-2.jpg', 'topics'],
            ['topic-1', 'Chuyên đề Quy hoạch động nâng cao (Advanced DP)', 'course-img-1.jpg', 'topics'],
            ['topic-2', 'Chuyên đề Thuật toán Đồ thị: Luồng cực đại & Khớp nối', 'course-img-2.jpg', 'topics'],
            ['book-3', 'Tuyển tập đề thi HSG Tin học các tỉnh & Quốc Gia 2020–2026', 'book-img-3.jpg', 'exams'],
            ['book-4', 'Bộ đề ôn thi vào lớp 10 chuyên Tin học', 'book-img-4.jpg', 'exams'],
            ['exam-1', 'Bộ 50 đề thi thử HSG Tin học cấp Tỉnh (Kèm Test & Lời giải)', 'contest-img-1.jpg', 'exams'],
            ['exam-2', 'Bộ đề thi thử Tốt nghiệp THPT môn Tin học 2026', 'contest-img-2.jpg', 'exams'],
        ];

        $out = [];
        foreach ($items as [$id, $title, $file, $group]) {
            $out[$id] = ['id' => $id, 'title' => $title, 'file' => $file, 'group' => $group];
        }

        return $out;
    }

    /**
     * Ảnh của một loại tài liệu, kèm url để hiển thị.
     *
     * @return list<array{id:string,title:string,url:string}>
     */
    public static function forType(?string $type): array
    {
        $group = self::GROUP_BY_TYPE[$type ?? ''] ?? null;
        if ($group === null) {
            return [];
        }

        $list = [];
        foreach (self::catalog() as $c) {
            if ($c['group'] === $group) {
                $list[] = ['id' => $c['id'], 'title' => $c['title'], 'url' => asset('assets/'.$c['file'])];
            }
        }

        return $list;
    }

    /** Toàn bộ catalog theo loại: ['book' => [...], 'topic' => [...], 'exam' => [...]]. */
    public static function byType(): array
    {
        $out = [];
        foreach (array_keys(self::GROUP_BY_TYPE) as $type) {
            $out[$type] = self::forType($type);
        }

        return $out;
    }

    public static function defaultIdFor(?string $type): ?string
    {
        return self::forType($type)[0]['id'] ?? null;
    }

    /** id có thuộc catalog của loại này không (chống gửi ảnh sai loại). */
    public static function belongsToType(?string $id, ?string $type): bool
    {
        foreach (self::forType($type) as $c) {
            if ($c['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    public static function marker(string $id): string
    {
        return self::PREFIX.$id;
    }

    public static function isCatalog(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PREFIX);
    }

    /** id catalog đang chọn từ giá trị lưu trong DB (null nếu là ảnh tải lên hoặc rỗng). */
    public static function idOf(?string $path): ?string
    {
        return self::isCatalog($path) ? substr($path, strlen(self::PREFIX)) : null;
    }

    /** URL ảnh bìa hiển thị — hiểu cả "catalog:<id>" lẫn đường dẫn ảnh tải lên cũ. Rỗng → null. */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (self::isCatalog($path)) {
            $c = self::catalog()[self::idOf($path)] ?? null;

            return $c ? asset('assets/'.$c['file']) : null;
        }

        return asset('storage/'.$path);
    }
}

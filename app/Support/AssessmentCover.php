<?php

namespace App\Support;

/**
 * SỬA 10/10 (khách: "ảnh bìa đề khi tạo và cập nhật đề cho chọn ảnh từ catalog; tìm ảnh mặc định của
 * source mới gắn sẵn") — catalog ảnh bìa ĐỀ. Ảnh lấy đúng các ảnh mà source mới (PracticePage.jsx,
 * examList) gắn cho thẻ đề: contest-img-1/2/3 và course-img-4.
 *
 * Chọn từ catalog thì lưu "catalog:<id>" vào assessments.cover_image_path (không sinh file, không cần
 * migration). Ảnh admin/giáo viên tải lên từ trước vẫn là đường dẫn tương đối trên disk public.
 * Assessment::coverUrl() hiểu cả hai nên mọi nơi hiện ảnh bìa đề tự đúng.
 */
final class AssessmentCover
{
    public const PREFIX = 'catalog:';

    /** @return array<string, array{id:string,title:string,file:string}> */
    public static function catalog(): array
    {
        $items = [
            ['exam-hsg-1', 'Đề luyện HSG Quốc gia · Vòng 1', 'contest-img-1.jpg'],
            ['exam-hsg-2', 'Đề luyện HSG Quốc gia · Vòng 2', 'course-img-4.jpg'],
            ['exam-chuyen', 'Đề thi thử vào 10 Chuyên Tin', 'contest-img-2.jpg'],
            ['exam-olympic', 'Olympic Tin học · Challenge', 'contest-img-3.jpg'],
        ];

        $out = [];
        foreach ($items as [$id, $title, $file]) {
            $out[$id] = ['id' => $id, 'title' => $title, 'file' => $file];
        }

        return $out;
    }

    /** @return list<array{id:string,title:string,url:string}> */
    public static function options(): array
    {
        return array_values(array_map(
            fn (array $c) => ['id' => $c['id'], 'title' => $c['title'], 'url' => asset('assets/'.$c['file'])],
            self::catalog()
        ));
    }

    /** Chuẩn hoá id nhận từ form: id lạ/rỗng -> null. */
    public static function normalize(mixed $id): ?string
    {
        $id = is_string($id) ? trim($id) : '';

        return $id !== '' && isset(self::catalog()[$id]) ? $id : null;
    }

    public static function marker(string $id): string
    {
        return self::PREFIX.$id;
    }

    public static function isCatalog(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PREFIX);
    }

    public static function idOf(?string $path): ?string
    {
        return self::isCatalog($path) ? substr($path, strlen(self::PREFIX)) : null;
    }

    /** URL ảnh bìa — hiểu cả "catalog:<id>" lẫn đường dẫn ảnh tải lên. Rỗng/không hợp lệ -> null. */
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

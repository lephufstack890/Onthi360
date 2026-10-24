<?php

namespace App\Support;

/**
 * SỬA 24/10 (khách: "cuộc thi thêm field chọn hình ảnh từ catalog mặc định như chỗ tài liệu") — catalog ảnh bìa
 * CUỘC THI / KHẢO SÁT. Cùng cách với App\Support\AssessmentCover: chọn từ catalog thì lưu "catalog:<id>" vào
 * competitions.image_path (không sinh file, không cần migration); ảnh tải lên vẫn là đường dẫn trên disk public.
 * Competition::imageUrl() hiểu cả hai.
 */
final class CompetitionCover
{
    public const PREFIX = 'catalog:';

    /** @return array<string, array{id:string,title:string,file:string}> */
    public static function catalog(): array
    {
        $items = [
            ['cup', 'Cúp vô địch', 'contest-img-1.jpg'],
            ['coding', 'Lập trình', 'contest-img-2.jpg'],
            ['survey', 'Khảo sát năng lực', 'contest-img-3.jpg'],
            ['achieve', 'Chinh phục thành tích', 'achieve-banner.jpg'],
            ['hsg', 'Đề thi HSG Tin học', 'cover-de-hsg.jpg'],
            ['vao-10', 'Ôn vào 10 chuyên Tin', 'cover-de-vao-10.jpg'],
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

    /** id nhận từ form: lạ / rỗng -> null. */
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

    /** URL ảnh của "catalog:<id>"; id không còn trong catalog -> null. */
    public static function url(string $path): ?string
    {
        $c = self::catalog()[self::idOf($path) ?? ''] ?? null;

        return $c ? asset('assets/'.$c['file']) : null;
    }
}

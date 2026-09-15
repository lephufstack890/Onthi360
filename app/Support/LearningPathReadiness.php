<?php

namespace App\Support;

use App\Models\Course;
use App\Models\LearningPath;

/**
 * Điều kiện để một lộ trình được phép ĐĂNG ra ngoài (A12).
 *
 * ── Vì sao tách hẳn thành một lớp riêng ──
 * Bộ luật này chắc chắn còn đổi: khách chưa chốt "bậc chưa có lớp thì chặn bán hay vẫn bán",
 * và khi làm xong phần bán thì sẽ có thêm luật "bậc chưa có giá". Gom vào một chỗ thì sau
 * này sửa luật là sửa đúng tệp này — không phải đi lùng trong Service, Controller và View.
 *
 * ── Hai mức ──
 * CHẶN    — không cho chuyển sang trạng thái "Đang hiển thị".
 * NHẮC    — vẫn cho đăng, chỉ hiện lời nhắc màu vàng ở màn quản trị.
 *
 * Muốn nâng một lời nhắc thành chặn (ví dụ khi khách chốt "chưa có lớp thì không được bán"),
 * đổi self::WARN thành self::BLOCK ở đúng dòng đó là xong.
 */
final class LearningPathReadiness
{
    public const BLOCK = 'block';
    public const WARN = 'warn';

    /**
     * @return list<array{level: string, message: string}>
     */
    public static function check(LearningPath $path): array
    {
        $issues = [];
        $courses = $path->relationLoaded('courses') ? $path->courses : $path->courses()->get();
        // Luật giá bên dưới đọc $course->product — nạp sẵn một lần cho cả danh sách.
        $courses->loadMissing('product');

        if ($courses->isEmpty()) {
            return [[
                'level' => self::BLOCK,
                'message' => 'Lộ trình chưa có bậc nào. Vào mục "Xếp bậc" thêm ít nhất một khoá học.',
            ]];
        }

        // Thiếu số buổi thì tổng buổi và tổng tuần đều sai — con số này in ra ngoài cho phụ
        // huynh xem nên không thể để trống.
        $missingSessions = $courses->filter(fn (Course $c) => (int) $c->session_count <= 0);
        if ($missingSessions->isNotEmpty()) {
            $issues[] = [
                'level' => self::BLOCK,
                'message' => 'Chưa điền số buổi cho: '.$missingSessions->pluck('title')->implode(', ').'.',
            ];
        }

        $missingLabels = $courses->filter(fn (Course $c) => trim((string) $c->level_code) === '');
        if ($missingLabels->isNotEmpty()) {
            $issues[] = [
                'level' => self::WARN,
                'message' => 'Chưa đặt mã bậc (PRE-CODE, FOUNDATION A, CƠ BẢN 1... tuỳ môn) cho: '.$missingLabels->pluck('title')->implode(', ').'.',
            ];
        }

        $missingOutcome = $courses->filter(fn (Course $c) => trim((string) $c->outcome) === '');
        if ($missingOutcome->isNotEmpty()) {
            $issues[] = [
                'level' => self::WARN,
                'message' => 'Chưa có câu kết quả ("Làm quen code"...) cho: '.$missingOutcome->pluck('title')->implode(', ').'.',
            ];
        }

        /*
         * Bậc chưa mở lớp nào: học sinh chọn xong sẽ không có lớp để vào.
         * Đang để mức NHẮC vì khách chưa trả lời câu "chưa có lớp thì chặn bán hay vẫn bán".
         * Chốt là chặn thì đổi self::WARN ở dòng dưới thành self::BLOCK.
         */
        $noOpenClass = $courses->filter(fn (Course $c) => $c->classRooms()->where('status', 'active')->doesntExist());
        if ($noOpenClass->isNotEmpty()) {
            $issues[] = [
                'level' => self::WARN,
                'message' => 'Chưa có lớp nào đang mở cho: '.$noOpenClass->pluck('title')->implode(', ').'.',
            ];
        }

        /*
         * C1 — Bậc chưa gắn sản phẩm bán.
         *
         * Để mức NHẮC chứ không CHẶN: lộ trình vẫn đăng được để phụ huynh xem trước chương
         * trình, chỉ là bậc đó chưa bấm mua được (ngoài trang công khai hiện "Liên hệ ghi
         * danh" thay cho nút mua). Khách chốt "chưa có giá thì cấm đăng" thì đổi self::WARN
         * ở dòng dưới thành self::BLOCK, không phải sửa chỗ nào khác.
         */
        $noProduct = $courses->filter(fn (Course $c) => $c->product_id === null);
        if ($noProduct->isNotEmpty()) {
            $issues[] = [
                'level' => self::WARN,
                'message' => 'Chưa gắn sản phẩm bán cho: '.$noProduct->pluck('title')->implode(', ').'. Mở màn Khoá học, mục "Bán khoá học này".',
            ];
        }

        // Có sản phẩm nhưng giá bằng 0 — gần như luôn là quên điền, nguy hiểm hơn chưa gắn
        // vì nút mua vẫn hiện và người ta mua được miễn phí.
        $zeroPrice = $courses->filter(fn (Course $c) => $c->product_id !== null && (int) ($c->product?->price ?? 0) <= 0);
        if ($zeroPrice->isNotEmpty()) {
            $issues[] = [
                'level' => self::BLOCK,
                'message' => 'Sản phẩm chưa điền giá (đang là 0đ) ở: '.$zeroPrice->pluck('title')->implode(', ').'.',
            ];
        }


        if ($path->outcomeList() === []) {
            $issues[] = [
                'level' => self::WARN,
                'message' => 'Chưa điền phần "Kết quả đầu ra" — đây là ba dòng in ở cuối ảnh lộ trình.',
            ];
        }

        return $issues;
    }

    /** Có được phép đăng không. */
    public static function canPublish(LearningPath $path): bool
    {
        foreach (self::check($path) as $issue) {
            if ($issue['level'] === self::BLOCK) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> Chỉ các lỗi chặn, để ghép vào thông báo trả về cho người dùng. */
    public static function blockers(LearningPath $path): array
    {
        return array_values(array_map(
            fn (array $i) => $i['message'],
            array_filter(self::check($path), fn (array $i) => $i['level'] === self::BLOCK),
        ));
    }
}

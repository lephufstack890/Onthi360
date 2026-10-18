<?php

namespace App\Support;

use App\Models\Assessment;

/**
 * SỬA 18/10 — BỘ LỌC tự gom đề của 1 bộ đề (products.bundle_filters). Giá trị bất biến, tự chuẩn hoá đầu vào.
 *
 * 4 tiêu chí đều là thông tin SẴN CÓ của đề luyện tập (màn tạo/sửa đề + exam.json của gói ZIP):
 *   categories : Cuộc thi — mã NHÓM của ExamCategory::filterGroup() (hsg_lop_9, hsg_lop_12, tin_hoc_tre, khac)
 *   levels     : Khối — mã ExamLevel::filterGroup() (thcs, thpt, khac)
 *   provinces  : Tỉnh/thành — mã ProvinceCatalog (assessments.province)
 *   year_from / year_to : Năm học — so theo năm ĐẦU TIÊN có 4 chữ số trong academic_year ("2024-2025" -> 2024)
 * Ô để trống = không lọc theo tiêu chí đó. Bộ lọc trống hoàn toàn = KHÔNG tự gom (bộ đề chỉ gồm đề ghim tay).
 *
 * Thêm tiêu chí mới sau này: thêm khoá ở fromArray()/toArray(), điều kiện ở matches(), chữ ở summary() và 1 ô ở
 * resources/views/admin/products/partials/exam-bundle.blade.php — không phải sửa chỗ nào khác.
 */
final class ExamBundleFilters
{
    /**
     * @param  list<string>  $categories
     * @param  list<string>  $levels
     * @param  list<string>  $provinces
     */
    private function __construct(
        public readonly array $categories,
        public readonly array $levels,
        public readonly array $provinces,
        public readonly ?int $yearFrom,
        public readonly ?int $yearTo,
    ) {}

    public static function fromArray(mixed $raw): self
    {
        $raw = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
        $pick = static function ($list, array $allowed): array {
            $list = is_array($list) ? $list : [];

            return array_values(array_unique(array_filter(array_map('strval', $list), fn ($v) => in_array($v, $allowed, true))));
        };
        $year = static fn ($v) => is_numeric($v) && (int) $v >= 1990 && (int) $v <= 2100 ? (int) $v : null;

        $from = $year($raw['year_from'] ?? null);
        $to = $year($raw['year_to'] ?? null);
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            $pick($raw['categories'] ?? [], array_keys(ExamCategory::CATEGORIES)),
            $pick($raw['levels'] ?? [], array_keys(ExamLevel::LEVELS)),
            $pick($raw['provinces'] ?? [], array_keys(ProvinceCatalog::all())),
            $from,
            $to,
        );
    }

    /** @return array{categories: list<string>, levels: list<string>, provinces: list<string>, year_from: ?int, year_to: ?int} */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'levels' => $this->levels,
            'provinces' => $this->provinces,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->categories === [] && $this->levels === [] && $this->provinces === []
            && $this->yearFrom === null && $this->yearTo === null;
    }

    /** Đề có khớp bộ lọc không (đề đã là đề luyện tập đã phát hành — kiểm ở ExamBundleService). */
    public function matches(Assessment $a): bool
    {
        if ($this->isEmpty()) {
            return false;
        }
        if ($this->categories !== [] && ! in_array(ExamCategory::filterGroup($a->exam_category), $this->categories, true)) {
            return false;
        }
        if ($this->levels !== [] && ! in_array(ExamLevel::filterGroup($a->exam_level), $this->levels, true)) {
            return false;
        }
        if ($this->provinces !== [] && ! in_array((string) $a->province, $this->provinces, true)) {
            return false;
        }
        if ($this->yearFrom !== null || $this->yearTo !== null) {
            $year = self::yearOf($a->academic_year);
            if ($year === null || ($this->yearFrom !== null && $year < $this->yearFrom) || ($this->yearTo !== null && $year > $this->yearTo)) {
                return false;
            }
        }

        return true;
    }

    /** Năm đầu tiên (4 chữ số) trong chuỗi năm học: "2024-2025" -> 2024, "Năm 2023" -> 2023, rỗng -> null. */
    public static function yearOf(?string $academicYear): ?int
    {
        return preg_match('/(19|20)\d{2}/', (string) $academicYear, $m) ? (int) $m[0] : null;
    }

    /** Một dòng mô tả bộ lọc cho admin, vd "HSG lớp 9 · THCS · Lâm Đồng, Bình Định · 2020–2025". */
    public function summary(): string
    {
        if ($this->isEmpty()) {
            return 'Chưa đặt bộ lọc — bộ đề chỉ gồm đề thêm tay.';
        }

        $parts = [];
        $parts[] = $this->categories ? implode(', ', array_map(fn ($c) => ExamCategory::CATEGORIES[$c] ?? $c, $this->categories)) : 'Mọi cuộc thi';
        $parts[] = $this->levels ? implode(', ', array_map(fn ($l) => ExamLevel::LEVELS[$l] ?? $l, $this->levels)) : 'Mọi khối';
        $parts[] = $this->provinces ? implode(', ', array_map(fn ($p) => ProvinceCatalog::label($p) ?? $p, $this->provinces)) : 'Mọi tỉnh/thành';
        $parts[] = match (true) {
            $this->yearFrom !== null && $this->yearTo !== null => $this->yearFrom.'–'.$this->yearTo,
            $this->yearFrom !== null => 'từ '.$this->yearFrom,
            $this->yearTo !== null => 'đến '.$this->yearTo,
            default => 'Mọi năm',
        };

        return implode(' · ', $parts);
    }
}

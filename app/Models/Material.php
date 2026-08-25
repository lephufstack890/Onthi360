<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory, Auditable;

    /**
     * Đọc bởi App\Concerns\Auditable — lý do khi admin từ chối/lưu trữ học liệu (10.4, 16 mục
     * 4). Material KHÔNG có cột deleted_at (không xóa mềm) — "gỡ" một Material khỏi
     * lưu hành dùng status=archived, không xóa bản ghi (Table 27 chỉ định nghĩa 4 trạng thái
     * nội dung: nháp/chờ duyệt/phát hành/lưu trữ, không có trạng thái "đã xóa").
     */
    public static ?string $auditReason = null;

    protected $fillable = [
        'product_id', 'parent_id', 'type', 'title', 'order', 'assessment_id', 'status',
        // SỬA 18/8 (Bộ đề, 16/8 mục 5) — chỉ có giá trị khi Material này được hệ thống TỰ
        // SINH ra từ việc cắt 1 PDF tổng của Bộ đề, xem migration add_page_range_to_materials.
        'page_from', 'page_to',
        // SỬA 25/8 (tải bài — từng bài/hàng loạt qua ZIP): 'code' duy nhất TRONG 1 sản phẩm
        // (product_id, code), không phải toàn hệ thống — xem migration
        // add_code_and_pdf_to_materials_table. 'pdf_path' lưu ở disk riêng tư 'local', xem
        // App\Services\Admin\ContentService::materialStore()/materialsBulkImportFromZip().
        'code', 'pdf_path', 'pdf_original_name',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Material::class, 'parent_id')->orderBy('order');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}

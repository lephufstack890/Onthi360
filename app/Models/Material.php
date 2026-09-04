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
        // SỬA 4/9 (khách yêu cầu: "file học liệu có thể là audio, pdf, ảnh động... đính nhiều
        // loại cùng lúc") — 2 cặp cột mới, ĐỘC LẬP với PDF ở trên — xem migration
        // add_audio_image_to_materials_table.
        'audio_path', 'audio_original_name', 'image_path', 'image_original_name',
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

    /** SỬA 4/9 — bài tập (Question) đã gắn vào chương/phần/đề này, xem Question::chapter(). */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'material_id');
    }

    /**
     * SỬA 4/9 (khách yêu cầu "Chương/Phần/Đề") — lọc đúng các record Material đóng vai trò
     * "chương/phần/đề" (mục lục gốc của 1 sản phẩm): type=chapter VÀ không có parent_id (bản
     * thân 1 chương không thể lồng trong chương khác). Dùng ở ContentService::
     * productChaptersFor() và nơi liệt kê lựa chọn "Thuộc chương/phần/đề" khi thêm bài
     * tập/học liệu.
     */
    public function scopeChapters($query)
    {
        return $query->where('type', 'chapter')->whereNull('parent_id');
    }
}

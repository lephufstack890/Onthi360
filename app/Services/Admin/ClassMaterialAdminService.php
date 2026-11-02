<?php

namespace App\Services\Admin;

use App\Enums\ClassMaterialStatus;
use App\Enums\ContentStatus;
use App\Models\AccessRight;
use App\Models\ClassMaterial;
use App\Models\ClassRoom;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 3/11 (khách: "admin có thể add tài liệu vào lớp giùm cho giáo viên nếu giáo viên chưa mua") — khối "Tài liệu của lớp" ở
 * trang Sửa lớp học (admin.classes.edit).
 *
 * Admin gắn NGUYÊN 1 sản phẩm (sách / chuyên đề / bộ đề) vào lớp, y như giáo viên tự gắn ở trang lớp
 * (Teacher\ClassRoomService::attachProduct) nhưng KHÔNG đòi giáo viên có quyền dạy (đã mua). Sau khi gắn:
 *   · học sinh trong lớp đọc / làm bài được (AccessGateService::hasActiveClassGrantedAccess — có sẵn);
 *   · giáo viên dạy lớp đọc / dạy được (AccessGateService::hasActiveTeacherClassAccess — thêm 3/11).
 * Gỡ = chuyển trạng thái "Đã gỡ" (không xoá lịch sử), cùng cách giáo viên gỡ.
 */
class ClassMaterialAdminService
{
    /** Loại sản phẩm gắn được vào lớp — cùng 3 loại trang lớp của giáo viên (Teacher\ClassRoomService::TYPE_LABELS). */
    public const TYPE_LABELS = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề'];

    /** @return array{attached: array<int, array<string, mixed>>, options: array<int, array{id:int, title:string, type:string, typeLabel:string}>} */
    public function panel(ClassRoom $classRoom): array
    {
        $rows = ClassMaterial::query()
            ->where('class_room_id', $classRoom->id)
            ->whereNull('material_id')
            ->where('status', ClassMaterialStatus::Active)
            ->with(['product:id,title,type', 'addedBy:id,name'])
            ->orderByDesc('added_at')
            ->get();

        $teacherIds = $classRoom->teachers()->pluck('users.id')->map(fn ($v) => (int) $v)->all();
        $owned = $teacherIds === [] || $rows->isEmpty() ? [] : AccessRight::query()
            ->whereIn('user_id', $teacherIds)
            ->whereIn('product_id', $rows->pluck('product_id')->all())
            ->where('status', \App\Enums\AccessRightStatus::Active)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('product_id')->map(fn ($v) => (int) $v)->unique()->all();

        $attached = $rows->filter(fn (ClassMaterial $cm) => $cm->product !== null)->map(fn (ClassMaterial $cm) => [
            'id' => (int) $cm->id,
            'title' => (string) $cm->product->title,
            'type' => self::TYPE_LABELS[$cm->product->type?->value ?? ''] ?? '',
            'typeKey' => $cm->product->type?->value ?? '',
            'addedBy' => $cm->addedBy?->name,
            'addedAt' => $cm->added_at?->format('d/m/Y'),
            'teacherOwns' => in_array((int) $cm->product_id, $owned, true),
            'removeUrl' => route('admin.classes.materials.detach', ['classRoom' => $classRoom->id, 'classMaterial' => $cm->id]),
        ])->values()->all();

        // SỬA 3/11 (khách: "thiết kế cho đẹp") — danh sách phẳng cho ô chọn có tab loại + tìm kiếm (partials/class-documents).
        $options = [];
        Product::query()
            ->where('status', ContentStatus::Published)
            ->whereIn('type', array_keys(self::TYPE_LABELS))
            ->whereNotIn('id', $rows->pluck('product_id')->all() ?: [0])
            ->orderBy('title')
            ->limit(2000)
            ->get(['id', 'title', 'type'])
            ->each(function (Product $p) use (&$options) {
                $options[] = ['id' => (int) $p->id, 'title' => (string) $p->title, 'type' => $p->type->value, 'typeLabel' => self::TYPE_LABELS[$p->type->value]];
            });

        return [
            'attached' => $attached,
            'options' => $options,
            'typeLabels' => self::TYPE_LABELS,
            'attachUrl' => route('admin.classes.materials.attach', $classRoom->id),
            'hasTeacher' => $teacherIds !== [],
        ];
    }

    /** Gắn nguyên sản phẩm vào lớp (đã gỡ trước đó thì bật lại). @return string tên tài liệu */
    public function attach(User $admin, ClassRoom $classRoom, int $productId): string
    {
        $product = Product::query()->find($productId);
        if ($product === null || $product->status !== ContentStatus::Published || ! isset(self::TYPE_LABELS[$product->type?->value ?? ''])) {
            throw ValidationException::withMessages(['product_id' => 'Chỉ thêm được sách, chuyên đề hoặc bộ đề đã phát hành.']);
        }

        $existing = ClassMaterial::query()
            ->where('class_room_id', $classRoom->id)
            ->where('product_id', $product->id)
            ->whereNull('material_id')
            ->first();

        if ($existing !== null) {
            $existing->fill([
                'status' => ClassMaterialStatus::Active,
                'removed_at' => null,
                'added_by' => $admin->id,
                'added_at' => now(),
            ])->save();
        } else {
            ClassMaterial::query()->create([
                'class_room_id' => $classRoom->id,
                'material_id' => null,
                'product_id' => $product->id,
                'release_version' => 1,
                'status' => ClassMaterialStatus::Active,
                'added_by' => $admin->id,
                'added_at' => now(),
            ]);
        }

        return (string) $product->title;
    }

    /** Gỡ khỏi lớp (đánh dấu "Đã gỡ", giữ lịch sử). @return string tên tài liệu */
    public function detach(ClassRoom $classRoom, int $classMaterialId): string
    {
        $cm = ClassMaterial::query()->where('class_room_id', $classRoom->id)->with('product:id,title')->findOrFail($classMaterialId);
        $cm->fill(['status' => ClassMaterialStatus::Removed, 'removed_at' => now()])->save();

        return (string) ($cm->product?->title ?? 'tài liệu');
    }
}

{{-- SỬA 9/10 — "Ảnh bìa từ catalog" (khách: chọn loại tài liệu xong thì cho chọn ảnh bìa, lấy từ catalog
     theo source mới). Bê từ CompactContentEditor.jsx (fieldset .cm-cover-catalog): lưới thẻ ảnh có radio,
     chỉ hiện ảnh của loại đang chọn; đổi loại mà ảnh đang chọn không thuộc loại mới → tự chọn ảnh đầu của
     loại mới. Thay cho ô tải ảnh lên cũ.

     Biến vào: $coverCatalog (loại → danh sách ảnh), $product (null khi tạo mới).
     Tự đứng một mình: nghe ô #type của form, không cần script toàn cục (server không có Vite). --}}
@php
    $coverCatalog = $coverCatalog ?? [];
    $currentPath = $product->cover_image_path ?? null;
    $originalType = isset($product) && $product ? $product->type->value : null;
    $type = old('type', $originalType ?? 'book');

    // Ảnh tải lên từ trước (không thuộc catalog) → cho phép "Giữ ảnh hiện tại".
    $customUrl = $currentPath && ! \App\Support\ProductCover::isCatalog($currentPath) ? \App\Support\ProductCover::url($currentPath) : null;

    $picked = old('cover_catalog');
    if ($picked === null) {
        $own = \App\Support\ProductCover::idOf($currentPath);
        $picked = $own && \App\Support\ProductCover::belongsToType($own, $type)
            ? $own
            : ($customUrl && $originalType === $type ? '' : (\App\Support\ProductCover::defaultIdFor($type) ?? ''));
    }

    $cfg = [
        'byType' => $coverCatalog,
        'type' => $type,
        'picked' => $picked,
        'customUrl' => $customUrl,
        'originalType' => $originalType,
    ];
@endphp
<fieldset class="apx-cc"
          x-data="{
              ...@js($cfg),
              get list() { return this.byType[this.type] || []; },
              get showCustom() { return !!this.customUrl && this.type === this.originalType; },
              init() {
                  const sel = document.getElementById('type');
                  if (!sel) return;
                  sel.addEventListener('change', () => {
                      this.type = sel.value;
                      const ok = this.list.some(c => c.id === this.picked) || (this.picked === '' && this.showCustom);
                      if (!ok) this.picked = this.list.length ? this.list[0].id : '';
                  });
              }
          }">
    <legend>Ảnh bìa từ catalog</legend>
    <div class="apx-cc__grid">
        <label class="apx-cc__item" x-show="showCustom" :class="{ 'is-on': picked === '' }">
            <input type="radio" name="cover_catalog" value="" x-model="picked">
            <img :src="customUrl" alt="" loading="lazy">
            <span>Giữ ảnh hiện tại (đã tải lên)</span>
        </label>
        <template x-for="c in list" :key="c.id">
            <label class="apx-cc__item" :class="{ 'is-on': picked === c.id }">
                <input type="radio" name="cover_catalog" :value="c.id" x-model="picked">
                <img :src="c.url" alt="" loading="lazy">
                <span x-text="c.title"></span>
            </label>
        </template>
    </div>
    <p class="apx-note" x-show="!list.length && !showCustom" style="margin:0">Loại này không có ảnh trong catalog.</p>
    @error('cover_catalog')
        <p class="apx-note" style="margin:6px 0 0;color:#dc2626">{{ $message }}</p>
    @enderror
</fieldset>

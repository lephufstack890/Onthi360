{{-- SỬA 12/11 (khách: "thêm chương / sửa chương load lâu quá") — danh sách chương tách ra partial để ProductChapterController trả
     lại riêng khối này (JSON) sau mỗi lần thêm / sửa / đổi thứ tự / xoá — không phải tải lại cả trang tài liệu (rất nặng vì danh
     sách bài tập). Biến: $product, $chapters, $chapterLabel. --}}
                        @if (empty($chapters))
                            <x-ws.empty-state :title="'Chưa có '.mb_strtolower($chapterLabel).' nào'" description="Thêm mục đầu tiên ở ô trên để bắt đầu gắn bài tập/học liệu." />
                        @else
                            <div class="apx-list">
                                @foreach ($chapters as $c)
                                    <div class="apx-item" x-show="editing !== {{ $c['id'] }}">
                                        <div class="apx-item__main">
                                            <p class="apx-item__title">{{ $c['title'] }}</p>
                                            <p class="apx-item__sub">
                                                @if ($c['hasPdf'])
                                                    <span class="apx-ok">✓ {{ $c['pdfName'] ?: 'Đã có PDF' }}</span>
                                                @else
                                                    <span class="apx-warn">⚠ Chưa có PDF nội dung</span>
                                                @endif
                                                · {{ $c['questionsCount'] }} bài tập
                                                @if (($c['materialsCount'] ?? 0) > 0)
                                                    · {{ $c['materialsCount'] }} học liệu
                                                @endif
                                            </p>
                                        </div>
                                        <div class="apx-item__side">
                                            {{-- SỬA 15/10 (khách: "nút mũi tên lên xuống để đảo thứ tự chương", "để gần nút Sửa/Xoá cho đẹp") —
                                                 đổi chỗ với mục liền trên/dưới; đây là thứ tự đọc của học sinh. --}}
                                            <div class="apx-move" role="group" aria-label="Đổi thứ tự">
                                                <form action="{{ route('admin.products.chapters.move', [$product->id, $c['id']]) }}" method="POST" data-apx-chapter-form>
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" class="apx-move__btn" title="Đưa lên trên" aria-label="Đưa {{ $c['title'] }} lên trên" {{ $loop->first ? 'disabled' : '' }}>
                                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.products.chapters.move', [$product->id, $c['id']]) }}" method="POST" data-apx-chapter-form>
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" class="apx-move__btn" title="Đưa xuống dưới" aria-label="Đưa {{ $c['title'] }} xuống dưới" {{ $loop->last ? 'disabled' : '' }}>
                                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                            <button type="button" @click="editing = {{ $c['id'] }}" class="acx-link" data-oi-perm="products.update">Sửa</button>
                                            <form action="{{ route('admin.products.chapters.destroy', [$product->id, $c['id']]) }}" method="POST" onsubmit="return confirm('Xoá mục này? Không thể hoàn tác.');" data-apx-chapter-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="acx-link acx-link--del">Xoá</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div x-show="editing === {{ $c['id'] }}" x-cloak>
                                        <form action="{{ route('admin.products.chapters.update', [$product->id, $c['id']]) }}" method="POST" enctype="multipart/form-data" class="apx-edit" data-apx-chapter-form>
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="title" value="{{ $c['title'] }}" required maxlength="255" class="apx-input apx-input--grow">
                                            <input type="number" name="order" value="{{ $c['order'] }}" min="0" class="apx-input apx-input--num" title="Thứ tự">
                                            {{-- SỬA 29/9 — thay/thêm tệp PDF của chính mục này. Bỏ trống = giữ tệp đang có. --}}
                                            <input type="file" name="pdf" accept="application/pdf" class="apx-file">
                                            @if ($c['hasPdf'])
                                                <label>
                                                    <input type="checkbox" name="remove_pdf" value="1"> Xoá PDF
                                                </label>
                                            @endif
                                            <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm">Lưu</button>
                                            <button type="button" @click="editing = null" class="acx-btn acx-btn--sm">Huỷ</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif

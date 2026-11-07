<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\PrintBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * SỬA 7/11 (khách: "thêm phần up cả quyển sách, chỉ lưu lại, xem trước trong này, có nút in và tải PDF — ai đặt bản in thì mình in
 * rồi ship") — CHỈ THÊM: tải lên / thay / xoá tệp PDF cả cuốn của 1 tài liệu, xem trước (mở thẳng trong trang), in, tải về. Lưu nội
 * bộ (App\Support\PrintBook), không đổi bất kỳ logic bán / đọc / quyền tài liệu nào.
 */
class ProductPrintBookController extends Controller
{
    /** admin.products.print-book.upload — tải lên (hoặc thay) tệp cả cuốn. Gọi bằng JS (có thanh tiến độ) thì trả JSON. */
    public function upload(Request $request, int $product): RedirectResponse|JsonResponse
    {
        abort_unless(PrintBook::ready(), 503, 'Chưa cập nhật cơ sở dữ liệu cho bản in cả cuốn — chạy php artisan migrate --force.');
        $model = Product::query()->findOrFail($product);

        $request->validate(
            ['book_pdf' => ['required', 'file', 'mimes:pdf', 'max:'.PrintBook::maxKilobytes()]],
            [
                'book_pdf.required' => 'Chọn tệp PDF cả cuốn sách.',
                'book_pdf.mimes' => 'Tệp phải là PDF.',
                'book_pdf.max' => 'Tệp vượt quá '.PrintBook::maxLabel().' mà máy chủ nhận được.',
                'book_pdf.uploaded' => 'Tải tệp lên không thành công — tệp có thể vượt quá giới hạn của máy chủ ('.PrintBook::maxLabel().').',
            ],
        );

        $file = $request->file('book_pdf');
        $path = $file->storeAs('print-books/'.$model->id, Str::random(24).'.pdf', PrintBook::DISK);
        $old = $model->print_book_path;

        $model->forceFill([
            'print_book_path' => $path,
            'print_book_name' => Str::limit((string) $file->getClientOriginalName(), 250, ''),
            'print_book_size' => (int) $file->getSize(),
            'print_book_uploaded_at' => now(),
            'print_book_uploaded_by' => Auth::id(),
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk(PrintBook::DISK)->delete($old);
        }

        $msg = $old ? 'Đã thay tệp bản in cả cuốn.' : 'Đã lưu tệp bản in cả cuốn.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $msg])
            : redirect()->to(route('admin.products.show', $model->id).'#ban-in-ca-cuon')->with('status', 'print-book-saved')->with('statusMessage', $msg);
    }

    /** admin.products.print-book.file — mở PDF trong trang (xem trước / in); ?tai=1 thì tải về máy. */
    public function file(Request $request, int $product): BinaryFileResponse
    {
        abort_unless(PrintBook::ready(), 404);
        $model = Product::query()->findOrFail($product);
        abort_unless(filled($model->print_book_path) && Storage::disk(PrintBook::DISK)->exists($model->print_book_path), 404, 'Tài liệu này chưa có tệp bản in cả cuốn.');

        $name = Str::slug(pathinfo((string) ($model->print_book_name ?: $model->title), PATHINFO_FILENAME) ?: 'ban-in-'.$model->id).'.pdf';
        $abs = Storage::disk(PrintBook::DISK)->path($model->print_book_path);
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex'];

        return $request->boolean('tai')
            ? response()->download($abs, $name, $headers)
            : response()->file($abs, $headers + ['Content-Disposition' => 'inline; filename="'.$name.'"']);
    }

    /** admin.products.print-book.destroy — xoá tệp bản in cả cuốn (chỉ tệp này, tài liệu giữ nguyên). */
    public function destroy(int $product): RedirectResponse
    {
        abort_unless(PrintBook::ready(), 503);
        $model = Product::query()->findOrFail($product);
        if ($model->print_book_path) {
            Storage::disk(PrintBook::DISK)->delete($model->print_book_path);
        }
        $model->forceFill(['print_book_path' => null, 'print_book_name' => null, 'print_book_size' => null, 'print_book_uploaded_at' => null, 'print_book_uploaded_by' => null])->save();

        return redirect()->to(route('admin.products.show', $model->id).'#ban-in-ca-cuon')->with('status', 'print-book-deleted')->with('statusMessage', 'Đã xoá tệp bản in cả cuốn.');
    }
}

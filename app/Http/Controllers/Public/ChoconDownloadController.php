<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * SỬA 4/11 — trang tải Chocon Desktop (nút "Tải Chocon Desktop" ở trang chủ) + tải file bộ cài.
 * File bộ cài đặt ở storage/app/chocon/<CHOCON_FILE> (xem config/chocon.php).
 */
class ChoconDownloadController extends Controller
{
    public function show(): View
    {
        return view('public.chocon-download', [
            'version' => config('chocon.version'),
            'available' => is_file($this->path()),
        ]);
    }

    public function file(): BinaryFileResponse|RedirectResponse
    {
        $path = $this->path();
        if (! is_file($path)) {
            return redirect()->route('chocon.download')->with('status', 'chocon-missing');
        }

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function path(): string
    {
        return storage_path('app/chocon/'.basename((string) config('chocon.file')));
    }
}

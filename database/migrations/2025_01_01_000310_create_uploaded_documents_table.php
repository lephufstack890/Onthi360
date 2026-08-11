<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Luồng nhập đề P0: tải -> OCR/trích xuất -> phân rã -> duyệt -> phát hành (6.4).
        Schema::create('uploaded_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 20)->default('uploaded'); // Enums\UploadedDocumentStatus
            $table->string('virus_scan_status', 20)->default('pending'); // pending|clean|infected
            $table->text('error_log')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_document_id')->constrained('uploaded_documents')->cascadeOnDelete();
            $table->string('extracted_text_path')->nullable();
            $table->json('confidence_map')->nullable(); // độ tin cậy theo vùng/trang nếu engine cung cấp
            $table->json('pages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_results');
    }
};

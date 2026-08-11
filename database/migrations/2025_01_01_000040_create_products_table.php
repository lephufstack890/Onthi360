<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // book|topic|exam|course — Enums\ProductType
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('subject', 60)->nullable();
            $table->string('grade', 20)->nullable();
            $table->string('topic', 120)->nullable();
            $table->unsignedInteger('price')->default(0); // VND, đơn vị nhỏ nhất
            $table->boolean('has_print_option')->default(false);
            $table->string('status', 20)->default('draft'); // Enums\ContentStatus
            $table->string('visibility', 20)->default('public'); // Enums\Visibility
            $table->string('owner_type', 20)->default('shared'); // Enums\OwnerType
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('duration_months')->nullable(); // thời hạn quyền mặc định khi kích hoạt
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'visibility']);
            $table->index(['subject', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

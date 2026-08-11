<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('locale', 10)->default('vi')->after('phone');
            $table->string('avatar_path')->nullable()->after('locale');
            $table->string('status', 20)->default('active')->after('avatar_path'); // active|suspended
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'locale', 'avatar_path', 'status']);
        });
    }
};

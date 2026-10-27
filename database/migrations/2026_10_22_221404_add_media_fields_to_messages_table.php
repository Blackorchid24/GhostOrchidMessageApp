<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('media_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('media_type', 50)->nullable();
            $table->string('media_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'media_path',
                'file_name',
                'media_type',
                'media_name',
                'latitude',
                'longitude',
            ]);
        });
    }
};
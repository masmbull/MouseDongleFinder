<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_photos', function (Blueprint $table): void {
            $table->id();
            $table->string('photoable_type');
            $table->unsignedBigInteger('photoable_id');
            $table->string('photo_type')->nullable();
            $table->string('caption')->nullable();
            $table->string('path');
            $table->string('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();

            $table->unique(['photoable_type', 'photoable_id', 'path'], 'device_photos_photoable_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_photos');
    }
};

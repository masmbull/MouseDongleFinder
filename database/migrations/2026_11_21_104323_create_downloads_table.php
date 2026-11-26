<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dongle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('identification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('file_path');
            $table->string('source_url');
            $table->string('source_type')->default('unknown'); // official, third-party, unknown
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('software_type')->nullable(); // driver, pairing, firmware, manual, etc.
            $table->string('file_size')->nullable();
            $table->string('sha256_hash')->nullable();
            $table->string('download_status')->default('completed');
            $table->timestamp('downloaded_at');
            $table->boolean('is_seed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};

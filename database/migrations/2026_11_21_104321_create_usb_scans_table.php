<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usb_scans', function (Blueprint $table) {
            $table->id();
            $table->string('scan_id')->unique();
            $table->json('devices')->nullable(); // full list of detected devices
            $table->integer('device_count')->default(0);
            $table->timestamp('scanned_at');
            $table->string('scanned_by')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usb_scans');
    }
};

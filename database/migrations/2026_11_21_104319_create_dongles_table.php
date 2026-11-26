<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dongles', function (Blueprint $table) {
            $table->id();
            $table->string('dongle_id')->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->text('physical_description')->nullable();
            $table->string('receiver_type')->nullable(); // Unifying, Nano, Lightspeed, Bolt, Bluetooth, etc.
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('vid')->nullable(); // USB Vendor ID
            $table->string('pid')->nullable(); // USB Product ID
            $table->string('hardware_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dongles');
    }
};

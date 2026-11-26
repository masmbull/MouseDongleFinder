<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mice', function (Blueprint $table) {
            $table->id();
            $table->string('mouse_id')->unique();
            $table->string('brand');
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->text('physical_description')->nullable();
            $table->string('connectivity_type')->nullable(); // 2.4GHz wireless, Bluetooth, etc.
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mice');
    }
};

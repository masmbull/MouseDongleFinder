<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usb_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vid', 4)->unique(); // 4-char hex
            $table->string('vendor_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usb_vendors');
    }
};

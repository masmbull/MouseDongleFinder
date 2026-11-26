<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usb_products', function (Blueprint $table) {
            $table->id();
            $table->string('vid', 4);
            $table->string('pid', 4);
            $table->string('product_name');
            $table->string('interface')->nullable();
            $table->timestamps();

            $table->unique(['vid', 'pid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usb_products');
    }
};

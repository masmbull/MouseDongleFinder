<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dongle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vid')->nullable();
            $table->string('pid')->nullable();
            $table->string('hardware_id')->nullable();
            $table->string('probable_manufacturer')->nullable();
            $table->string('probable_model')->nullable();
            $table->string('device_category')->nullable();
            $table->string('wireless_technology')->nullable();
            $table->string('frequency')->nullable();
            $table->string('receiver_family')->nullable();
            $table->json('compatible_mice')->nullable();
            $table->string('pairing_utility')->nullable();
            $table->string('official_support_url')->nullable();
            $table->json('evidence')->nullable();
            $table->string('confidence')->nullable(); // high, medium, low, unknown
            $table->json('research_sources')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identifications');
    }
};

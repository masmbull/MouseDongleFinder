<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mouse_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->string('vid', 4)->nullable();
            $table->string('pid', 4)->nullable();
            $table->string('receiver_family')->nullable();
            $table->string('wireless_technology')->nullable();
            $table->string('frequency')->nullable();
            $table->string('pairing_utility')->nullable();
            $table->string('official_support_url')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['brand', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouse_profiles');
    }
};

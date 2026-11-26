<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dongle_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['matched', 'suspected', 'unmatched', 'unknown'])->default('unknown');
            $table->text('notes')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_matches');
    }
};

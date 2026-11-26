<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['scanning', 'observing', 'completed', 'abandoned'])->default('scanning');
            $table->json('dongles')->nullable();
            $table->json('observations')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_sessions');
    }
};

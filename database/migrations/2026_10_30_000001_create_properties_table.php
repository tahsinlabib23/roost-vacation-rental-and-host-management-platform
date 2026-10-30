<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('location', 180);
            $table->decimal('nightly_rate', 10, 2)->default(0);
            $table->string('status', 24)->default('draft')->index();
            $table->string('cover_image_url', 2048)->nullable();
            $table->unsignedSmallInteger('guest_capacity')->default(2);
            $table->timestamps();
            $table->index(['host_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

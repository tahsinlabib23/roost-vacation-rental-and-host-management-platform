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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'guest_id', 'host_id']);
            $table->index(['guest_id', 'last_message_at']);
            $table->index(['host_id', 'last_message_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

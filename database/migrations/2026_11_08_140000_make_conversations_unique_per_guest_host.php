<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatePairs = DB::table('conversations')
            ->select('guest_id', 'host_id')
            ->groupBy('guest_id', 'host_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicatePairs as $pair) {
            $conversationIds = DB::table('conversations')
                ->where('guest_id', $pair->guest_id)
                ->where('host_id', $pair->host_id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            $canonicalId = $conversationIds->shift();

            DB::table('messages')
                ->whereIn('conversation_id', $conversationIds)
                ->update(['conversation_id' => $canonicalId]);

            DB::table('conversations')->whereIn('id', $conversationIds)->delete();

            DB::table('conversations')
                ->where('id', $canonicalId)
                ->update([
                    'last_message_at' => DB::table('messages')
                        ->where('conversation_id', $canonicalId)
                        ->max('created_at'),
                    'updated_at' => now(),
                ]);
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->index('property_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'guest_id', 'host_id']);
            $table->unique(['guest_id', 'host_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['guest_id', 'host_id']);
            $table->unique(['property_id', 'guest_id', 'host_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['property_id']);
        });
    }
};

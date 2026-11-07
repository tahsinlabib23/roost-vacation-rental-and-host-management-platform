<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessagingSchemaRollbackTest extends TestCase
{
    public function test_messaging_schema_rolls_back_in_dependency_order(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $conversationMigration = require database_path('migrations/2026_11_08_040021_create_conversations_table.php');
        $messageMigration = require database_path('migrations/2026_11_08_040023_create_messages_table.php');

        try {
            $messageMigration->down();
            $conversationMigration->down();

            $this->assertFalse(Schema::hasTable('messages'));
            $this->assertFalse(Schema::hasTable('conversations'));
            $this->assertTrue(Schema::hasTable('properties'));
            $this->assertTrue(Schema::hasTable('users'));
        } finally {
            if (! Schema::hasTable('conversations')) {
                $conversationMigration->up();
            }

            if (! Schema::hasTable('messages')) {
                $messageMigration->up();
            }
        }
    }
}

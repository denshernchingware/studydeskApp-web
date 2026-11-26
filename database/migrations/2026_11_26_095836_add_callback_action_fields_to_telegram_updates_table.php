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
        Schema::table('telegram_updates', function (Blueprint $table): void {
            $table->string('callback_token_hash', 64)->nullable()->after('callback_query_id');
            $table->string('telegram_message_id', 64)->nullable()->after('callback_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_updates', function (Blueprint $table): void {
            $table->dropColumn(['callback_token_hash', 'telegram_message_id']);
        });
    }
};

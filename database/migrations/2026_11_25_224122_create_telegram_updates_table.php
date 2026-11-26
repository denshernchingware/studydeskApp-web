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
        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('update_type', 24);
            $table->string('telegram_user_id', 64)->nullable();
            $table->string('chat_id', 64)->nullable();
            $table->string('callback_query_id', 128)->nullable();
            $table->string('link_token_hash', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['processed_at', 'update_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
    }
};

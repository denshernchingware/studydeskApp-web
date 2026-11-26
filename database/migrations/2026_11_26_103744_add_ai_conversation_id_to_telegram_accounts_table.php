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
        Schema::table('telegram_accounts', function (Blueprint $table): void {
            $table->string('ai_conversation_id', 36)->nullable();
            $table->foreignId('ai_conversation_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_conversation_semester_id');
            $table->dropColumn('ai_conversation_id');
        });
    }
};

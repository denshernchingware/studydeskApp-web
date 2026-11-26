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
        Schema::table('telegram_deliveries', function (Blueprint $table): void {
            $table->foreignId('semester_id')->nullable()->after('timetable_slot_id')->constrained()->nullOnDelete();
            $table->text('message_text')->nullable()->after('notification_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('semester_id');
            $table->dropColumn('message_text');
        });
    }
};

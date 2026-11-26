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
            $table->boolean('weekly_summary_enabled')->default(true);
            $table->unsignedTinyInteger('weekly_summary_day')->default(0);
            $table->time('weekly_summary_time')->default('18:00:00');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'weekly_summary_enabled',
                'weekly_summary_day',
                'weekly_summary_time',
            ]);
        });
    }
};

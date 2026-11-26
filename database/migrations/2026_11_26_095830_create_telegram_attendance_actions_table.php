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
        Schema::create('telegram_attendance_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_slot_id')->constrained()->cascadeOnDelete();
            $table->string('status', 7);
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['telegram_delivery_id', 'status']);
            $table->index(['user_id', 'timetable_slot_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_attendance_actions');
    }
};

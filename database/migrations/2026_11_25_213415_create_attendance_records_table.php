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
        Schema::table('timetable_slots', function (Blueprint $table): void {
            $table->unique(['user_id', 'id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('timetable_slot_id');
            $table->date('session_date');
            $table->string('status', 7);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign(['user_id', 'timetable_slot_id'])
                ->references(['user_id', 'id'])
                ->on('timetable_slots')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'timetable_slot_id', 'session_date']);
            $table->index(['user_id', 'session_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');

        Schema::table('timetable_slots', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'id']);
        });
    }
};

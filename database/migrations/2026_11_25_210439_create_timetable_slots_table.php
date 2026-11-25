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
        Schema::table('subjects', function (Blueprint $table): void {
            $table->unique(['user_id', 'id']);
        });

        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label', 60)->nullable();
            $table->date('effective_from');
            $table->timestamps();

            $table->foreign(['user_id', 'subject_id'])
                ->references(['user_id', 'id'])
                ->on('subjects')
                ->cascadeOnDelete();

            $table->index(['user_id', 'weekday', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'id']);
        });
    }
};

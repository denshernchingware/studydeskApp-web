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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->index(['user_id', 'starts_on']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_semester_id')
                ->nullable()
                ->constrained('semesters')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_semester_id');
        });

        Schema::dropIfExists('semesters');
    }
};

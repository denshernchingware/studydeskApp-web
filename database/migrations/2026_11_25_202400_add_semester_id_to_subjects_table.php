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
            $table->unsignedBigInteger('semester_id')->nullable()->after('user_id');
            $table->index(['user_id', 'semester_id', 'name']);
            $table->foreign(['user_id', 'semester_id'])
                ->references(['user_id', 'id'])
                ->on('semesters')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropForeign(['user_id', 'semester_id']);
            $table->dropIndex(['user_id', 'semester_id', 'name']);
            $table->dropColumn('semester_id');
        });
    }
};

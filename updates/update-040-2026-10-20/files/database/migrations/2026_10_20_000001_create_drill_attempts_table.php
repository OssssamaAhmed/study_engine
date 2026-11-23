<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inline per-drill checks from the exercise page — one row per drill
     * (latest answer wins). No mode column: scopes through exercise_id,
     * same as attempts.
     */
    public function up(): void
    {
        Schema::create('drill_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('drill_key', 8);
            $table->text('answer');
            $table->unsignedInteger('score')->nullable();
            $table->text('feedback_md')->nullable();
            $table->timestamps();
            $table->unique(['exercise_id', 'drill_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drill_attempts');
    }
};

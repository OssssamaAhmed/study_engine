<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('phase_number')->unique();
            $table->string('name');
            $table->unsignedInteger('week_start');
            $table->unsignedInteger('week_end');
        });

        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->text('raw_text');
            $table->string('source_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('summary');
            $table->timestamp('first_seen_at')->useCurrent();
            $table->float('ease')->default(2.5);
            $table->integer('interval_days')->default(0);
            $table->date('next_review_at')->nullable();
            $table->integer('lapses')->default(0);
            $table->boolean('retired')->default(false);
        });

        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            // ponytail: embeddings as JSON + cosine in PHP; swap to sqlite-vec if scale demands
            $table->json('embedding')->nullable();
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->text('mission_md');
            $table->string('status')->default('pending'); // pending|submitted|evaluated
        });

        Schema::create('exercise_concept', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // new|review
            $table->primary(['exercise_id', 'concept_id']);
        });

        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->text('pasted_result');
            $table->text('evaluation_md')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('struggle_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->unsignedTinyInteger('weight')->default(1); // 1-3
            $table->boolean('resolved')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('struggle_notes');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('exercise_concept');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('chunks');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('transcripts');
        Schema::dropIfExists('topics');
    }
};

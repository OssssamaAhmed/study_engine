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
        Schema::create('phase_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('phase_number')->unique();
            $table->string('status')->default('in_progress'); // in_progress | pending_project | completed
            $table->text('mega_project_md')->nullable();
            $table->text('project_submission')->nullable();
            $table->text('project_eval_md')->nullable();
            $table->unsignedInteger('project_score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // checkpoint_near | cert_near | phase_ready | general
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('exam_prep_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('exam_target'); // e.g. "Checkpoint 1", "CCA-F Certification"
            $table->string('method'); // active_recall | spaced_1357 | test_maker | metacognitive
            $table->text('study_guide_text')->nullable();
            $table->json('session_data')->nullable(); // questions, choices, distractor analysis, etc.
            $table->text('user_submission')->nullable(); // answers, metacognitive journal
            $table->text('evaluation_md')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_prep_sessions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('phase_progress');
    }
};

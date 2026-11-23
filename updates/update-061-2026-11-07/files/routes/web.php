<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamPrepController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PhaseController;
use App\Http\Controllers\SideQuestController;
use App\Models\Topic;
use App\Support\LearnerInstructions;
use App\Support\LearnerName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);
Route::post('/exercise/generate', [DashboardController::class, 'generate']);
Route::get('/exercise/{exercise}', [App\Http\Controllers\ExerciseController::class, 'show']);
Route::post('/exercise/{exercise}/submit', [App\Http\Controllers\ExerciseController::class, 'submit']);
Route::post('/exercise/{exercise}/drill/{key}', [App\Http\Controllers\ExerciseController::class, 'checkDrill']);
Route::post('/exercise/{exercise}/reset', [App\Http\Controllers\ExerciseController::class, 'reset']);
Route::post('/exercise/{exercise}/note', [App\Http\Controllers\ExerciseController::class, 'note']);
Route::post('/exercise/{exercise}/add-more', [App\Http\Controllers\ExerciseController::class, 'addMore']);

Route::get('/chat', [App\Http\Controllers\ChatController::class, 'show']);
Route::post('/chat', [App\Http\Controllers\ChatController::class, 'send']);
Route::post('/chat/clear', [App\Http\Controllers\ChatController::class, 'clear']);
Route::post('/chat/mission', [App\Http\Controllers\ChatController::class, 'saveMission']);

Route::get('/concepts', [App\Http\Controllers\ConceptController::class, 'index']);
Route::post('/concepts/{concept}/retire', [App\Http\Controllers\ConceptController::class, 'toggleRetire']);
Route::post('/notes/{note}/resolve', [App\Http\Controllers\ConceptController::class, 'resolveNote']);

// Phase validation & Mega Portfolio Project routes
Route::get('/phases', [PhaseController::class, 'index']);
Route::get('/phases/{phase}', [PhaseController::class, 'show']);
Route::get('/phases/{phase}/research-prompt', [PhaseController::class, 'researchPrompt']);
Route::post('/phases/{phase}/project', [PhaseController::class, 'generateProject']);
Route::post('/phases/{phase}/project-from-research', [PhaseController::class, 'generateFromResearch']);
Route::post('/phases/{phase}/implementation-plan', [PhaseController::class, 'generateImplementationPlan']);
Route::post('/phases/{phase}/skeleton', [PhaseController::class, 'generateSkeleton']);Route::get('/phases/{phase}/export/{document}/{format}', [PhaseController::class, 'export']);
Route::get('/phases/{phase}/audit-prompt', [PhaseController::class, 'auditPrompt']);
Route::get('/phases/{phase}/fix-plan-prompt', [PhaseController::class, 'fixPlanPrompt']);
Route::post('/phases/{phase}/submit', [PhaseController::class, 'submitProject']);
Route::post('/phases/{phase}/complete', [PhaseController::class, 'attemptComplete']);

// Notifications center routes
Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
Route::post('/notifications/{id}/delete', [NotificationController::class, 'destroy']);

// Exam preparation hub routes
Route::get('/exam-prep', [ExamPrepController::class, 'index']);
Route::post('/exam-prep/ingest', [ExamPrepController::class, 'ingest']);
Route::post('/exam-prep/start', [ExamPrepController::class, 'startSession']);
Route::get('/exam-prep/session/{id}', [ExamPrepController::class, 'showSession']);
Route::post('/exam-prep/session/{id}/submit', [ExamPrepController::class, 'submitSession']);

// Side Quest — targeted micro-ladder drills
Route::get('/side-quest', [SideQuestController::class, 'index']);
Route::post('/side-quest', [SideQuestController::class, 'store']);

Route::get('/history', [App\Http\Controllers\HistoryController::class, 'index'])->name('history');
Route::delete('/history/{exercise}', [App\Http\Controllers\HistoryController::class, 'destroy']);

Route::get('/settings', function () {
    return view('settings', [
        'instructions' => LearnerInstructions::get(),
        'name' => LearnerName::get(),
    ]);
});

Route::post('/settings/mode', function (Request $request, App\Services\RoadmapService $roadmap) {
    $data = $request->validate([
        'mode' => ['required', 'in:'.App\Support\StudyMode::AI_SECURITY.','.App\Support\StudyMode::LARAVEL_WEB],
    ]);

    App\Support\StudyMode::set($data['mode']);

    // First-ever switch to a track with no topics yet — auto-sync so the
    // dashboard never lands on an empty/null-phase state.
    if (App\Models\Topic::count() === 0) {
        $roadmap->syncTopics();
    }

    return redirect('/')->with('status', 'Switched to '.App\Support\StudyMode::label().'.');
});

Route::post('/settings/profile', function (Request $request) {
    $data = $request->validate(['name' => ['nullable', 'string', 'max:60']]);
    LearnerName::set($data['name'] ?? '');

    return redirect('/settings')->with('status', 'Name saved — the dashboard will greet you with it.');
});

Route::post('/settings/reset', function (Request $request, App\Services\RoadmapService $roadmap) {
    $request->validate(
        ['confirm' => ['required', 'in:RESET']],
        ['confirm.required' => 'Type RESET (all capitals) to confirm.', 'confirm.in' => 'Type RESET (all capitals) to confirm.'],
    );

    $mode = App\Support\StudyMode::current();

    // Wipe only the ACTIVE track's progress, child tables first (foreign
    // keys). This track's topics stay; the other track is untouched.
    DB::transaction(function () use ($mode) {
        $exerciseIds = App\Models\Exercise::anyMode()->where('mode', $mode)->pluck('id');
        $conceptIds = App\Models\Concept::anyMode()->where('mode', $mode)->pluck('id');

        DB::table('exam_prep_sessions')->where('mode', $mode)->delete();
        DB::table('notifications')->where('mode', $mode)->delete();
        DB::table('phase_progress')->where('mode', $mode)->delete();
        DB::table('attempts')->whereIn('exercise_id', $exerciseIds)->delete();
        DB::table('drill_attempts')->whereIn('exercise_id', $exerciseIds)->delete();
        DB::table('exercise_concept')->whereIn('exercise_id', $exerciseIds)->delete();
        DB::table('exercises')->where('mode', $mode)->delete();
        DB::table('struggle_notes')->whereIn('concept_id', $conceptIds)->delete();
        DB::table('chunks')->where('mode', $mode)->delete();
        DB::table('concepts')->where('mode', $mode)->delete();
        DB::table('transcripts')->where('mode', $mode)->delete();
    });

    // Personal instructions are shared across both tracks (not scoped), so a
    // single-track reset must leave them alone — clearing them here would
    // silently wipe the OTHER track's instructions too.
    App\Support\ActivityLog::clear($mode);
    session()->forget('chat_'.$mode);

    // This track's roadmap restarts today for a fresh learner.
    $file = $roadmap->activeFilePath();
    $data = json_decode(File::get($file), true);
    $data['meta']['start_date'] = today()->toDateString();
    File::put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return redirect('/settings')->with('status', App\Support\StudyMode::label($mode).' progress erased — fresh start. The roadmap begins today.');
});

Route::post('/settings/instructions', function (Request $request) {
    $data = $request->validate(['instructions' => ['nullable', 'string', 'max:4000']]);
    LearnerInstructions::set($data['instructions'] ?? '');

    return redirect('/settings')->with('status', 'Personal instructions saved — every new mission and tutor chat will follow them.');
});

Route::post('/settings/resync', function (App\Services\RoadmapService $roadmap) {
    $count = $roadmap->syncTopics();

    return redirect('/settings')->with('status', "Roadmap resynced — {$count} topics.");
});

Route::get('/ingest', [IngestController::class, 'show']);
Route::post('/ingest/preview', [IngestController::class, 'preview']);
Route::post('/ingest/confirm', [IngestController::class, 'confirm']);

// Milestone 1 verification route: all topics seeded from the roadmap JSON.
Route::get('/debug/topics', fn () => Topic::orderBy('phase_number')->get());
